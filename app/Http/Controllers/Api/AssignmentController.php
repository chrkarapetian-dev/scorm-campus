<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OpenAiRequestFailed;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\ScormCourse;
use App\Models\User;
use App\Services\AssignmentEvaluator;
use App\Services\CourseChunkSearch;
use App\Services\CourseTextRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AssignmentController extends Controller
{
    public function store(Request $request, CourseTextRepository $texts, CourseChunkSearch $search, AssignmentEvaluator $evaluator): JsonResponse
    {
        if (strlen($request->getContent()) > 100_000) {
            return $this->error('invalid', 422);
        }
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return $this->error('invalid', 400);
        }
        $answer = is_string($body['student_response'] ?? null)
            ? preg_replace('/\A[\s\p{Z}\x{200B}\x{FEFF}]+|[\s\p{Z}\x{200B}\x{FEFF}]+\z/u', '', $body['student_response']) ?? ''
            : '';
        if (mb_strlen($answer) < 200) {
            return $this->error('minimum', 422);
        }
        if (mb_strlen($answer) > 6000) {
            return $this->error('maximum', 422);
        }
        if (Validator::make($body, ['course_id' => ['required', 'string', 'size:16']])->fails()) {
            return $this->error('course_required', 422);
        }
        $course = ScormCourse::available()->find($body['course_id']);
        if (! $course || ! $texts->hasText($course)) {
            return $this->error('unavailable', 404);
        }
        $question = $course->assignmentQuestion();
        $excerpts = $search->forAssignment($course, $question);
        if ($excerpts === []) {
            return $this->error('source_unavailable', 503);
        }
        $threshold = (int) config('scorm.assignments.ai_threshold');
        $model = config('services.openai.model');
        $hash = hash('sha256', json_encode([AssignmentEvaluator::VERSION, $question, $answer, $excerpts, $threshold, $model], JSON_UNESCAPED_UNICODE));
        $token = bin2hex(random_bytes(16));
        $userId = $request->user()->id;

        // Serialize reservations per account in MySQL. No transaction is held during network calls.
        // The unique index and token also protect identical retries and expired-lease workers.
        $reservation = DB::transaction(function () use ($userId, $course, $question, $answer, $hash, $threshold, $model, $token) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $existing = AssignmentSubmission::where('user_id', $userId)->where('course_id', $course->id)->where('submission_hash', $hash)->lockForUpdate()->first();
            if ($existing && in_array($existing->status, ['evaluated', 'flagged'], true)) {
                return ['completed' => $existing];
            }
            if (AssignmentSubmission::where('user_id', $userId)->where('status', 'processing')->where('processing_expires_at', '>', now())->lockForUpdate()->first(['id']) !== null) {
                return ['busy' => true];
            }
            $submission = $existing ?? new AssignmentSubmission([
                'user_id' => $userId, 'course_id' => $course->id,
                'assignment_question' => $question, 'answer' => $answer, 'submission_hash' => $hash,
                'ai_threshold' => $threshold, 'model' => $model,
            ]);
            $submission->fill([
                'status' => 'processing', 'processing_token' => $token,
                'processing_expires_at' => now()->addSeconds(2 * config('services.openai.timeout') + 30),
            ])->save();

            return ['submission' => $submission];
        }, 3);
        if (isset($reservation['completed'])) {
            return response()->json($this->result($reservation['completed']));
        }
        if (isset($reservation['busy'])) {
            return $this->error('busy', 409);
        }
        $submission = $reservation['submission'];
        try {
            $result = $evaluator->evaluate($question, $answer, $excerpts, $threshold);
            foreach (['detection_details', 'evaluation_feedback'] as $field) {
                if (is_array($result[$field])) {
                    $result[$field] = json_encode($result[$field], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                }
            }
            $updated = AssignmentSubmission::whereKey($submission->id)->where('processing_token', $token)
                ->update($result + ['processing_token' => null, 'processing_expires_at' => null]);
            if ($updated !== 1) {
                return $this->error('busy', 409);
            }
        } catch (Throwable $e) {
            AssignmentSubmission::whereKey($submission->id)->where('processing_token', $token)->update([
                'status' => 'failed', 'processing_token' => null, 'processing_expires_at' => null,
            ]);
            if ($e instanceof OpenAiRequestFailed) {
                Log::warning('Assignment AI failed', ['code' => $e->errorCode ?? 'invalid_or_unavailable']);
            } else {
                report($e);
            }

            return $this->error('failed', 503);
        }

        return response()->json($this->result($submission->fresh()));
    }

    public function index(Request $request): JsonResponse
    {
        if (! is_string($request->query('course_id'))) {
            return $this->error('course_required', 422);
        }
        $query = AssignmentSubmission::where('user_id', $request->user()->id)
            ->where('course_id', $request->query('course_id'))->whereIn('status', ['evaluated', 'flagged']);
        $before = $request->query('before', '');
        if (is_string($before) && ctype_digit($before)) {
            $query->where('id', '<', $request->query('before'));
        }
        $rows = $query->latest('id')->limit(11)->get();
        $page = $rows->take(10);

        return response()->json([
            'submissions' => $page->map(fn ($row) => $this->result($row))->values(),
            'next_before' => $rows->count() > 10 ? $page->last()->id : null,
        ]);
    }

    public function show(Request $request, int $submission): JsonResponse
    {
        $row = AssignmentSubmission::where('user_id', $request->user()->id)->whereIn('status', ['evaluated', 'flagged'])->findOrFail($submission);

        return response()->json($this->result($row));
    }

    private function result(AssignmentSubmission $row): array
    {
        return [
            'id' => $row->id, 'course_id' => $row->course_id,
            'assignment_question' => $row->assignment_question, 'answer' => $row->answer,
            'status' => $row->status, 'final_score' => $row->final_score,
            'ai_probability' => $row->ai_probability, 'classification' => $row->ai_classification,
            'reason' => $row->ai_reason, 'ai_threshold' => $row->ai_threshold,
            'feedback' => $row->evaluation_feedback, 'created_at' => $row->created_at->toIso8601String(),
        ];
    }

    private function error(string $key, int $status): JsonResponse
    {
        return response()->json(['error' => __("assignment.$key")], $status);
    }
}
