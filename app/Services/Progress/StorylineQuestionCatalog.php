<?php

namespace App\Services\Progress;

use App\Models\ScormCourse;
use Illuminate\Support\Facades\Cache;

/** Read published question definitions as data; never execute a package's JavaScript. */
class StorylineQuestionCatalog
{
    public function forCourse(?ScormCourse $course): array
    {
        if ($course === null) {
            return [];
        }

        $path = $course->storagePath().'/html5/data/js/data.js';
        clearstatcache(true, $path);
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 8 * 1024 * 1024) {
            return [];
        }

        $key = 'storyline-questions:v2:'.hash('sha256', $path.'|'.filesize($path).'|'.filemtime($path));

        return Cache::remember($key, 86400, fn () => $this->extract($this->decode(file_get_contents($path))));
    }

    private function decode(string $source): array
    {
        // Match only the wrapper, then decode the string in a linear scan. A repeated
        // regex over a whole published course can exhaust PCRE's JIT stack.
        $source = ltrim($source, "\xEF\xBB\xBF");
        if (! preg_match("~^\\s*window\\.globalProvideData\\(\\s*'data'\\s*,\\s*'~", $source, $match)) {
            return [];
        }
        $end = strrpos($source, "'");
        if ($end === false || ! preg_match('~^\\s*\\);?\\s*$~', substr($source, $end + 1))) {
            return [];
        }
        $literal = substr($source, strlen($match[0]), $end - strlen($match[0]));
        $json = '';
        for ($i = 0, $length = strlen($literal); $i < $length; $i++) {
            $char = $literal[$i];
            if ($char === "'") {
                return [];
            }
            if ($char !== '\\') {
                $json .= $char;

                continue;
            }
            if (++$i >= $length) {
                return [];
            }
            $json .= match ($literal[$i]) {
                "'" => "'", '"' => '"', '\\' => '\\',
                'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0c",
                default => '\\'.$literal[$i],
            };
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    private function extract(array $data): array
    {
        if (! is_array($data['scenes'] ?? null) || ! is_array($data['quizzes'] ?? null)) {
            return [];
        }
        $slides = [];
        foreach ($data['scenes'] as $scene) {
            if (! is_array($scene) || ! is_array($scene['slides'] ?? null)) {
                continue;
            }
            foreach ($scene['slides'] ?? [] as $slide) {
                if (is_array($slide) && is_string($slide['id'] ?? null)) {
                    $slides[$slide['id']] = [$scene, $slide];
                }
            }
        }
        $questions = [];
        foreach ($data['quizzes'] as $quiz) {
            if (! is_array($quiz) || ! is_array($quiz['sliderefs'] ?? null)) {
                continue;
            }
            foreach ($quiz['sliderefs'] ?? [] as $ref) {
                if (! is_array($ref) || ! is_string($ref['id'] ?? null)) {
                    continue;
                }
                $parts = explode('.', $ref['id'] ?? '');
                [$scene, $slide] = $slides[end($parts)] ?? [[], []];
                if (! is_array($slide['interactions'] ?? null)) {
                    continue;
                }
                foreach ($slide['interactions'] as $index => $interaction) {
                    if (! is_array($interaction) || ! is_array($interaction['choices'] ?? null) || ! is_array($interaction['answers'] ?? null)) {
                        continue;
                    }
                    if (($interaction['type'] ?? '') !== 'multiplechoice'
                        || ! is_string($scene['lmsId'] ?? null) || $scene['lmsId'] === ''
                        || ! is_string($slide['lmsId'] ?? null) || $slide['lmsId'] === ''
                        || ! is_string($interaction['lmsId'] ?? null) || $interaction['lmsId'] === ''
                        || ! is_string($interaction['lmstext'] ?? $slide['title'] ?? '')) {
                        continue;
                    }
                    $choices = [];
                    $codes = [];
                    foreach ($interaction['choices'] as $choice) {
                        if (! is_array($choice) || ! is_string($choice['id'] ?? null) || ! is_string($choice['lmstext'] ?? null)) {
                            continue;
                        }
                        $choices[$choice['id']] = $this->text($choice['lmstext'] ?? '');
                        $codes[$choice['id']] = $this->legacyCode($choice['lmstext'] ?? '');
                    }
                    $correct = [];
                    foreach ($interaction['answers'] ?? [] as $answer) {
                        if (! is_array($answer) || ($answer['status'] ?? '') !== 'correct' || ! is_array($answer['evaluate']['statements'] ?? null)) {
                            continue;
                        }
                        foreach ($answer['evaluate']['statements'] ?? [] as $statement) {
                            if (! is_array($statement) || ! is_string($statement['choiceid'] ?? null)) {
                                continue;
                            }
                            $id = str_replace('choices.', '', $statement['choiceid'] ?? '');
                            if (($statement['kind'] ?? '') === 'equals' && isset($choices[$id])) {
                                $correct[] = $choices[$id];
                            }
                        }
                    }
                    $id = $scene['lmsId'].'_'.$slide['lmsId'].'_'.$interaction['lmsId']."_{$index}_0";
                    $questions[$id] = [
                        'questionId' => $id,
                        'number' => count($questions) + 1,
                        'description' => $this->text($interaction['lmstext'] ?? $slide['title'] ?? ''),
                        'choices' => $choices,
                        'codes' => $codes,
                        'correct' => array_values(array_unique($correct)),
                    ];
                }
            }
        }

        return $questions;
    }

    private function text(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public function display(array $question, ?array $answer): array
    {
        $selected = is_string($answer['selected'] ?? null) ? $answer['selected'] : null;
        $matches = [];
        if ($selected !== null && $selected !== '') {
            foreach ($question['choices'] as $id => $text) {
                // Use the published text to recreate this package's lossy SCORM 1.2 encoding.
                if (in_array($selected, [$text, $id, 'choices.'.$id, $question['codes'][$id]], true)) {
                    $matches[] = $text;
                }
            }
        }
        $answered = $selected !== null && $selected !== '';
        $result = match ($answer['result'] ?? null) {
            'correct' => 'correct', 'wrong', 'incorrect' => 'incorrect', default => null,
        };

        return [
            'questionId' => $question['questionId'],
            'number' => $question['number'],
            'description' => $question['description'],
            'selected' => count($matches) === 1 ? $matches[0] : null,
            'correct' => $answered ? $question['correct'] : [],
            'answered' => $answered,
            'selectionUnavailable' => $answered && count($matches) !== 1,
            'result' => $answered ? $result : null,
        ];
    }

    public function fallback(string $id, array $answer, int $number): array
    {
        $selected = is_string($answer['selected'] ?? null) ? $answer['selected'] : null;
        $answered = $selected !== null && $selected !== '';
        $readable = $answered && ! preg_match('/_{3,}/', $selected);
        $correct = array_values(array_filter(is_array($answer['correct'] ?? null) ? $answer['correct'] : [], fn ($value) => is_string($value) && ! preg_match('/_{3,}/', $value)));

        return [
            'questionId' => $id, 'number' => $number,
            'description' => is_string($answer['description'] ?? null) && $answer['description'] !== '' ? $this->text($answer['description']) : null,
            'selected' => $readable ? $selected : null,
            'correct' => $correct, 'answered' => $answered,
            'selectionUnavailable' => $answered && ! $readable,
            'result' => match ($answer['result'] ?? null) {
                'correct' => 'correct', 'wrong', 'incorrect' => 'incorrect', default => null,
            },
        ];
    }

    private function legacyCode(string $text): string
    {
        $text = preg_replace('/^\s+|\s+$/u', '', $text);
        // Mirrors CreateValidIdentifierLegacy in the bundled SCORM driver, including its ASCII-only \w.
        if (str_starts_with(strtolower($text), 'urn:')) {
            $text = substr($text, 4);
        }

        return preg_replace("/[^a-zA-Z0-9_\\-()+.:=@;\$!*'%]/u", '_', $text);
    }
}
