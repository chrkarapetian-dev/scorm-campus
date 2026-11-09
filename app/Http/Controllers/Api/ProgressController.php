<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseProgress;
use App\Models\ScormCourse;
use App\Services\Progress\ProgressSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgressController extends Controller
{
    public function show(Request $request, string $course, ProgressSummary $summary)
    {
        $model = ScormCourse::available()->findOrFail($course);

        return response()->json($summary->make(CourseProgress::with('answers')->where('user_id', $request->user()->id)->where('course_id', $course)->first(), $model))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, string $course, ProgressSummary $summary)
    {
        $model = ScormCourse::available()->findOrFail($course);
        $data = $request->validate([
            'revision' => 'required|integer|min:0',
            'runtime' => 'sometimes|array|max:2000',
            'runtime.*' => 'present|string|max:262144',
            'slide' => 'sometimes|nullable|string|max:100',
            'video' => 'sometimes|array:duration,ranges',
            'video.duration' => 'required_with:video|numeric|gt:0|max:86400',
            'video.ranges' => 'required_with:video|array|max:1000',
            'video.ranges.*' => 'array|size:2',
            'video.ranges.*.*' => 'numeric|min:0|max:86400',
            'reset' => 'sometimes|boolean',
        ]);
        foreach ($data['runtime'] ?? [] as $key => $value) {
            if (! preg_match('/^cmi\.[a-zA-Z0-9_.]+$/D', $key)) {
                throw ValidationException::withMessages(['runtime' => 'Առաջընթացի տվյալներն անվավեր են։']);
            }
        }

        return DB::transaction(function () use ($request, $course, $data, $summary, $model) {
            // Serializes first creation as well as updates, on SQLite and MySQL.
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $record = CourseProgress::firstOrCreate(['user_id' => $request->user()->id, 'course_id' => $course], ['runtime' => [], 'activity' => [], 'revision' => 0]);
            if ($record->revision !== $data['revision']) {
                return response()->json(['error' => 'Առաջընթացը փոխվել է այլ պատուհանում։ Վերբեռնեք էջը։', 'progress' => $summary->make($record, $model)], 409);
            }
            if ($data['reset'] ?? false) {
                $record->runtime = [];
                $record->activity = [];
                $record->answers()->delete();
            } else {
                $runtime = array_replace($record->runtime, $data['runtime'] ?? []);
                if (strlen(json_encode($runtime)) > 1048576) {
                    throw ValidationException::withMessages(['runtime' => 'Առաջընթացի տվյալները չափազանց մեծ են։']);
                }
                $activity = $record->activity;
                $slide = $data['slide'] ?? $runtime['cmi.location'] ?? $runtime['cmi.core.lesson_location'] ?? null;
                $next = null;
                foreach (config('scorm.storyline_sections') as $section) {
                    if ($slide && (str_contains($slide, $section['slide']) || (isset($section['resultsSlide']) && str_contains($slide, $section['resultsSlide'])))) {
                        $next = $section['id'];
                    }
                    if ($slide && isset($section['resultsSlide']) && str_contains($slide, $section['resultsSlide'])) {
                        $activity['quizSubmitted'] = true;
                    }
                }
                if ($next !== null) {
                    $prior = $activity['activeSection'] ?? null;
                    if ($prior !== $next && in_array($prior, ['materials', 'literature'], true)) {
                        $activity[$prior]['completed'] = true;
                    }
                    $activity['activeSection'] = $next;
                    $activity[$next]['visited'] = true;
                }
                if (isset($data['video'])) {
                    $duration = (float) $data['video']['duration'];
                    $ranges = array_merge($activity['video']['ranges'] ?? [], $data['video']['ranges']);
                    usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
                    $merged = [];
                    foreach ($ranges as [$start, $end]) {
                        if ($end < $start || $end > $duration + 1) {
                            throw ValidationException::withMessages(['video' => 'Տեսանյութի ժամանակն անվավեր է։']);
                        }
                        $end = min($end, $duration);
                        if ($start >= $end) {
                            continue;
                        }
                        $last = count($merged) - 1;
                        if ($last >= 0 && $start <= $merged[$last][1]) {
                            $merged[$last][1] = max($end, $merged[$last][1]);
                        } else {
                            $merged[] = [$start, $end];
                        }
                    }
                    $activity['video'] = ['duration' => $duration, 'ranges' => $merged];
                }
                $record->runtime = $runtime;
                $record->activity = $activity;
                $interactions = [];
                foreach ($runtime as $key => $value) {
                    if (preg_match('/^cmi\.interactions\.(\d+)\.(.+)$/', $key, $match)) {
                        $interactions[(int) $match[1]][$match[2]] = $value;
                    }
                }
                ksort($interactions);
                foreach ($interactions as $index => $interaction) {
                    if (! array_filter(array_keys($data['runtime'] ?? []), fn ($key) => str_starts_with($key, "cmi.interactions.{$index}."))) {
                        continue;
                    }
                    $id = $interaction['id'] ?? '';
                    if ($id === '') {
                        continue;
                    }
                    if (mb_strlen($id) > 191) {
                        throw ValidationException::withMessages(['runtime' => 'Հարցի նույնացուցիչը չափազանց երկար է։']);
                    }
                    // The stable SCORM question ID, not its transient interaction index, identifies an answer.
                    $record->answers()->updateOrCreate(['question_id' => $id], ['data' => [
                        'description' => $interaction['description'] ?? null,
                        'type' => $interaction['type'] ?? null,
                        'selected' => $interaction['learner_response'] ?? $interaction['student_response'] ?? null,
                        'correct' => array_values(array_filter($interaction, fn ($key) => preg_match('/^correct_responses\.\d+\.pattern$/', $key), ARRAY_FILTER_USE_KEY)),
                        'result' => $interaction['result'] ?? null,
                    ]]);
                }
            }
            $record->revision++;
            $record->save();

            return response()->json($summary->make($record->load('answers'), $model))->header('Cache-Control', 'no-store');
        }, 3);
    }
}
