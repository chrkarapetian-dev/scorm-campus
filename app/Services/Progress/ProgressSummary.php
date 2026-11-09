<?php

namespace App\Services\Progress;

use App\Models\CourseProgress;
use App\Models\ScormCourse;

class ProgressSummary
{
    public function __construct(private readonly StorylineQuestionCatalog $catalog) {}

    public function make(?CourseProgress $record, ?ScormCourse $course = null): array
    {
        $runtime = $record?->runtime ?? [];
        $activity = $record?->activity ?? [];
        $sections = [];
        foreach (config('scorm.storyline_sections') as $definition) {
            $id = $definition['id'];
            $sections[$id] = ['visited' => (bool) ($activity[$id]['visited'] ?? false), 'completed' => false];
            if (in_array($id, ['materials', 'literature'], true)) {
                $sections[$id]['completed'] = (bool) ($activity[$id]['completed'] ?? false);
            }
        }
        $video = $activity['video'] ?? [];
        $duration = (float) ($video['duration'] ?? 0);
        $seconds = array_sum(array_map(fn ($range) => $range[1] - $range[0], $video['ranges'] ?? []));
        $percent = $duration > 0 ? min(100, round(100 * $seconds / $duration)) : 0;
        $sections['video'] = ['seconds' => floor($seconds), 'duration' => $duration, 'percent' => $percent, 'visited' => $seconds > 0, 'completed' => $duration > 0 && $seconds >= $duration * .9];
        $status = $runtime['cmi.completion_status'] ?? $runtime['cmi.core.lesson_status'] ?? '';
        $success = $runtime['cmi.success_status'] ?? $status;
        $finished = ($activity['quizSubmitted'] ?? false) || in_array($status, ['completed', 'passed', 'failed'], true) || in_array($success, ['passed', 'failed'], true);
        $raw = $runtime['cmi.score.raw'] ?? $runtime['cmi.core.score.raw'] ?? null;
        $min = $runtime['cmi.score.min'] ?? $runtime['cmi.core.score.min'] ?? 0;
        $max = $runtime['cmi.score.max'] ?? $runtime['cmi.core.score.max'] ?? 100;
        $score = is_numeric($raw) && is_numeric($min) && is_numeric($max) && $max > $min
            ? max(0, min(100, (int) round(100 * ($raw - $min) / ($max - $min)))) : null;
        $definitions = $this->catalog->forCourse($course ?? $record?->course);
        $savedAnswers = $record?->answers->keyBy('question_id') ?? collect();
        $answers = [];
        foreach ($definitions as $id => $question) {
            $answers[] = $this->catalog->display($question, is_array($savedAnswers->get($id)?->data) ? $savedAnswers->get($id)->data : null);
        }
        foreach ($savedAnswers->sortBy('question_id', SORT_NATURAL) as $answer) {
            if ($definitions === []) {
                $answers[] = $this->catalog->fallback($answer->question_id, is_array($answer->data) ? $answer->data : [], count($answers) + 1);
            }
        }
        $answered = count(array_filter($answers, fn ($answer) => $answer['answered']));
        // A package can report a score before its attempt is submitted. Display it as
        // provisional, but never use it to mark the quiz completed or passed.
        $quiz = $finished && $score !== null ? ['score' => $score, 'passed' => $score >= 70] : null;
        $quizScore = $score !== null && ($answered > 0 || $finished) ? ['score' => $score, 'final' => $finished] : null;
        $sections['quiz'] = ['visited' => $answered > 0 || $quiz !== null, 'completed' => $quiz['passed'] ?? false, 'score' => $quiz['score'] ?? null];
        $done = count(array_filter($sections, fn ($section) => $section['completed']));

        return ['status' => $finished ? $success : ($record && ($runtime !== [] || $activity !== []) ? 'in_progress' : 'not_started'), 'revision' => $record?->revision ?? 0, 'scorm' => $runtime, 'sections' => $sections, 'quiz' => $quiz, 'quizScore' => $quizScore, 'answers' => $answers, 'quizProgress' => ['answered' => $answered, 'total' => $definitions !== [] ? count($definitions) : null], 'completedSections' => $done, 'totalSections' => count($sections), 'completionPercent' => count($sections) ? (int) round(100 * $done / count($sections)) : 0, 'activeSection' => $activity['activeSection'] ?? null];
    }
}
