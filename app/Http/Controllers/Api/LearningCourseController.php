<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\LearningCourse;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningCourseController extends Controller
{
    public function show(): JsonResponse
    {
        $course = LearningCourse::main();

        return response()->json([
            'id' => $course->id,
            'title' => $course->title,
            'lessons' => $course->lessons->map(fn (Lesson $lesson) => $lesson->only(['id', 'kind', 'title', 'label', 'lead', 'duration'])),
            'goals' => $course->goals->map(fn (Goal $goal) => [
                'n' => $goal->number,
                'title' => $goal->title,
                'short' => $goal->summary,
                'group' => $goal->group_name,
            ]),
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $course = LearningCourse::main();
        $progress = $request->json('progress');
        $progress = is_array($progress) ? $progress : [];

        $completed = is_array($progress['completed'] ?? null) ? $progress['completed'] : [];
        $seconds = is_array($progress['seconds'] ?? null) ? $progress['seconds'] : [];
        $quiz = is_array($progress['quiz'] ?? null) ? $progress['quiz'] : null;
        $total = array_sum(array_map(fn ($value) => is_numeric($value) ? (float) $value : 0, $seconds));
        $lessonCount = $course->lessons->count();

        $item = [
            'course_id' => $course->id,
            'title' => $course->title,
            'ui_synopsis_armenian' => 'Ինդիկատորների, համաթվերի և ՄԱԿ-ի 17 նպատակների ներածական դասընթաց։',
            'manifest_path' => '/',
            'badge_text_armenian' => $completed ? 'Շարունակել' : 'Նոր դասընթաց',
            'progress_percentage' => count($completed) === $lessonCount ? 100 : min(99, (int) round($total / 2400 * 100)),
            'journey_summary_armenian' => [
                'total_time_formatted' => $total ? ((int) floor($total / 60)).'ր' : 'Նոր դասընթաց',
                'video_time_seconds' => (float) ($seconds['video'] ?? 0),
                'slides_time_seconds' => (float) ($seconds['slides'] ?? 0),
                'literature_time_seconds' => (float) ($seconds['literature'] ?? 0),
                'quiz_accuracy_percentage' => ! empty($quiz['total']) ? (int) round(($quiz['correct'] ?? 0) / $quiz['total'] * 100) : 0,
            ],
        ];

        $needsReview = ($quiz !== null && ($quiz['correct'] ?? 0) < ($quiz['total'] ?? 0)) || ! in_array('reflection', $completed, true);

        return response()->json(['curated_rows' => [
            ['row_id' => 'continue', 'category_title_armenian' => 'Շարունակել ուսումնառությունը', 'items' => count($completed) < $lessonCount ? [$item] : []],
            ['row_id' => 'review', 'category_title_armenian' => 'Կրկնության և պրակտիկայի կարիք ունեցող', 'items' => $needsReview ? [['badge_text_armenian' => 'Կրկնել և ամրապնդել'] + $item] : []],
            ['row_id' => 'all', 'category_title_armenian' => 'Բոլոր դասընթացները', 'items' => [['badge_text_armenian' => 'Հայերեն · 5 բաժին'] + $item]],
        ]]);
    }
}
