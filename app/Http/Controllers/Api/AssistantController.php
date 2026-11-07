<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OpenAiRequestFailed;
use App\Http\Controllers\Controller;
use App\Models\AiQuestion;
use App\Models\ScormCourse;
use App\Services\CourseAssistant;
use App\Services\CourseChunkSearch;
use App\Services\CourseTextRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssistantController extends Controller
{
    private const MAX_BODY_BYTES = 100_000;

    public function __construct(
        private readonly CourseAssistant $assistant,
        private readonly CourseChunkSearch $search,
        private readonly CourseTextRepository $texts,
    ) {}

    public function question(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);

        if ($body === null) {
            return response()->json(['error' => 'Հարցման տվյալները սխալ են։'], 400);
        }

        $question = $this->text($body, 'student_question');
        $maxLength = config('services.openai.question_max_length');

        if ($question === '') {
            return response()->json(['error' => 'Գրեք ձեր հարցը։'], 400);
        }

        if (mb_strlen($question) > $maxLength) {
            return response()->json(['error' => "Հարցը կարող է պարունակել առավելագույնը {$maxLength} նիշ։"], 422);
        }

        $course = ScormCourse::available()->find($this->text($body, 'course_id'));

        if ($course === null || ! $this->texts->hasText($course)) {
            return response()->json(['error' => 'Այս դասընթացի համար AI օգնականը հասանելի չէ։'], 404);
        }

        $user = $request->user();

        if ($user->aiQuestionsRemaining() === 0) {
            return $this->limitReached();
        }

        $excerpts = $this->search->relevant($course, $question);

        // Nothing to send, so no AI call is made and no question is used.
        if ($excerpts === []) {
            return response()->json([
                'answer' => CourseAssistant::NOT_FOUND_ANSWER,
                'questions_remaining' => $user->aiQuestionsRemaining(),
            ]);
        }

        if (! $user->reserveAiQuestion()) {
            return $this->limitReached();
        }

        // Only an answer that was generated and recorded may keep the question.
        try {
            $result = $this->assistant->answer($course, $question, $excerpts);

            // The excerpts turned out not to hold the answer: as promised in the UI, it isn't counted.
            if (! $result['found']) {
                $user->releaseAiQuestion();

                return response()->json([
                    'answer' => CourseAssistant::NOT_FOUND_ANSWER,
                    'questions_remaining' => $user->aiQuestionsRemaining(),
                ]);
            }

            AiQuestion::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'question' => $question,
                'answer' => $result['answer'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
            ]);
        } catch (OpenAiRequestFailed $e) {
            $user->releaseAiQuestion();
            Log::error('AI Q&A error: '.($e->errorCode ?? $e->getMessage()));

            return response()->json([
                'error' => 'AI ծառայությունը ժամանակավորապես անհասանելի է։ Փորձեք կրկին․ այս հարցը չի հաշվվել։',
                'questions_remaining' => $user->aiQuestionsRemaining(),
            ], 503);
        } catch (Throwable $e) {
            $user->releaseAiQuestion();

            throw $e;
        }

        return response()->json(['answer' => $result['answer'], 'questions_remaining' => $user->aiQuestionsRemaining()]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);

        if ($body === null) {
            return response()->json(['error' => 'Հարցման տվյալները սխալ են։'], 400);
        }

        $answer = mb_substr($this->text($body, 'student_response'), 0, 6000);

        if (mb_strlen($answer) < 40) {
            return response()->json(['error' => 'Գրեք առնվազն 40 նիշ՝ հարցին ամբողջական պատասխանելու համար։'], 400);
        }

        return response()->json($this->assistant->evaluate($answer));
    }

    private function limitReached(): JsonResponse
    {
        return response()->json(['error' => 'Դուք օգտագործել եք AI-ին հարց տալու ձեր բոլոր հնարավորությունները։', 'questions_remaining' => 0], 403);
    }

    private function jsonBody(Request $request): ?array
    {
        $content = $request->getContent();

        if (strlen($content) > self::MAX_BODY_BYTES) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function text(array $body, string $key): string
    {
        $value = $body[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
