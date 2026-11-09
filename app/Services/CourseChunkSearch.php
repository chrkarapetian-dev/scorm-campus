<?php

namespace App\Services;

use App\Models\ScormCourse;

/**
 * Picks the course text the model reads. A course that fits the token budget is sent whole: the
 * model then sees everything and handles rephrased, synonym or foreign-language questions that a
 * keyword search misses, and the identical text is cached by OpenAI between requests. Only a text
 * over the budget is cut down to its best chunks, ranked with BM25 over rough Armenian stems.
 */
class CourseChunkSearch
{
    private const K1 = 1.2;

    private const B = 0.75;

    private const STOPWORDS = [
        'է', 'են', 'էր', 'էին', 'եմ', 'ես', 'ենք', 'եք', 'և', 'եւ', 'ու', 'ի', 'այս', 'այդ', 'այն', 'որ', 'ով', 'ովքեր', 'ում', 'ինչ', 'ինչու', 'ինչպես', 'ինչպիսի',
        'ինչն', 'ինչը', 'ինչի', 'ինչով', 'ինչից', 'որն', 'որը', 'որի', 'որոնք', 'որոնց', 'որտեղ', 'երբ', 'քանի', 'մեջ', 'համար', 'մասին', 'հետ', 'կամ', 'բայց', 'եթե',
        'ապա', 'նաև', 'մի', 'մեկ', 'իսկ', 'ըստ', 'առ', 'դեպի', 'ինձ', 'դու', 'նա', 'մենք', 'դուք', 'նրանք', 'իմ', 'քո', 'կա', 'չի', 'չէ', 'կարող', 'պետք', 'ասա', 'ասել',
        'րդ', 'րորդ', 'ունի', 'ունեն', 'կարող', 'կարո', 'բացատրիր', 'բացատրեք', 'բացատրել', 'ներկայացրու', 'ներկայացրեք', 'նկարագրիր', 'նկարագրեք', 'թվարկիր', 'թվարկեք', 'դասընթաց', 'դասընթացում',
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'what', 'why', 'how', 'who', 'when', 'where', 'which', 'of', 'in', 'on', 'to', 'for', 'and', 'or', 'do', 'does',
        'explain', 'tell', 'me', 'about', 'course',
    ];

    /** Longest first. Stripped repeatedly, so "զարգացումը" and "զարգացման" both reach "զարգաց". */
    private const SUFFIXES = [
        'ություններից', 'ություններով', 'ություններում', 'ությունների', 'ությունները', 'ությունից', 'ությունով', 'ությունում', 'ություններ', 'ությունը', 'ությունն', 'ություն',
        'ությամբ', 'ության', 'ներից', 'ներով', 'ներում', 'ներին', 'ների', 'ները', 'ներն', 'երից', 'երով', 'երում', 'երին', 'երի', 'երը', 'ներ', 'ման', 'ում', 'ից', 'ով', 'ին', 'ան', 'եր', 'ը', 'ն', 'ի',
    ];

    private const MIN_STEM_LENGTH = 3;

    private const MIN_PREFIX_LENGTH = 4;

    public function __construct(private readonly CourseTextRepository $texts) {}

    /**
     * The material to send for this question, in reading order. Empty only when the course has no
     * text, or (for an oversized text) when the search finds nothing that covers the question.
     *
     * @return list<array{heading: string, text: string}>
     */
    public function relevant(ScormCourse $course, string $question): array
    {
        return $this->wholeText($course)
            ?? $this->ranked($course, $question, config('scorm.search.min_coverage'), config('scorm.search.min_relative_score'), config('scorm.search.max_chunks'));
    }

    /** Fixed question-based evidence, independent of the learner's answer, so every learner is graded against the same text. */
    public function forAssignment(ScormCourse $course, string $question): array
    {
        return $this->wholeText($course) ?? $this->ranked($course, $question, 0, 0, config('scorm.assignments.max_chunks'));
    }

    /** @return ?list<array{heading: string, text: string}> */
    private function wholeText(ScormCourse $course): ?array
    {
        $tokens = $this->texts->tokens($course);

        if ($tokens === 0 || $tokens > config('scorm.context.full_text_max_tokens')) {
            return null;
        }

        return [['heading' => $course->title, 'text' => $this->texts->text($course)]];
    }

    private function ranked(ScormCourse $course, string $question, float $minCoverage, float $minRelativeScore, int $maxChunks): array
    {
        $chunks = $this->texts->chunks($course);
        $queryTerms = array_values(array_unique(self::terms($question)));

        if ($chunks === [] || $queryTerms === []) {
            return [];
        }

        $total = count($chunks);
        $averageLength = max(1, array_sum(array_column($chunks, 'length')) / $total);
        $frequencies = array_map(fn (array $chunk) => array_map(fn (string $term) => $this->frequency($chunk, $term), $queryTerms), $chunks);

        $weights = [];

        foreach ($queryTerms as $t => $term) {
            $found = count(array_filter($frequencies, fn (array $row) => $row[$t] > 0));
            $weights[$t] = log(1 + ($total - $found + 0.5) / ($found + 0.5));
        }

        $scored = [];

        foreach ($chunks as $c => $chunk) {
            $score = 0.0;
            $matched = 0;

            foreach ($frequencies[$c] as $t => $frequency) {
                if ($frequency > 0) {
                    $score += $weights[$t] * $frequency * (self::K1 + 1)
                        / ($frequency + self::K1 * (1 - self::B + self::B * $chunk['length'] / $averageLength));
                    $matched++;
                }
            }

            // Rarity only ranks here: as a gate it lets one unmatched word veto the course's own main terms.
            // A chunk that passes wrongly is caught by the model, which reports it found no answer.
            if ($score > 0 && $matched / count($queryTerms) >= $minCoverage) {
                $scored[$c] = $score;
            }
        }

        if ($scored === []) {
            return [];
        }

        arsort($scored);

        $best = reset($scored);
        $selected = [];
        $seen = [];

        foreach ($scored as $c => $score) {
            if (count($selected) === $maxChunks || $score < $best * $minRelativeScore) {
                break;
            }

            if (! isset($seen[$chunks[$c]['text']])) {
                $seen[$chunks[$c]['text']] = true;
                $selected[$c] = ['heading' => $chunks[$c]['heading'], 'text' => $chunks[$c]['text']];
            }
        }

        ksort($selected);

        return array_values($selected);
    }

    /** @return list<string> stems of the meaningful words in the text */
    public static function terms(string $text): array
    {
        // Armenian question and emphasis marks sit inside words: "Ինչո՞ւ".
        $text = mb_strtolower(str_replace(['՞', '՛', '՜', '՚', '՟', 'ՙ'], '', $text));

        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        $terms = [];

        foreach ($matches[0] as $word) {
            if (mb_strlen($word) >= 2 && ! in_array($word, self::STOPWORDS, true)) {
                $terms[] = self::stem($word);
            }
        }

        return $terms;
    }

    private static function stem(string $word): string
    {
        do {
            $stripped = false;

            foreach (self::SUFFIXES as $suffix) {
                if (str_ends_with($word, $suffix) && mb_strlen($word) - mb_strlen($suffix) >= self::MIN_STEM_LENGTH) {
                    $word = mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
                    $stripped = true;

                    break;
                }
            }
        } while ($stripped);

        return $word;
    }

    /** Also counts longer words starting with the term, so "Հռոմի" finds "հռոմեական". */
    private function frequency(array $chunk, string $term): int
    {
        if (mb_strlen($term) < self::MIN_PREFIX_LENGTH) {
            return $chunk['terms'][$term] ?? 0;
        }

        $frequency = 0;

        foreach ($chunk['terms'] as $candidate => $count) {
            if (str_starts_with((string) $candidate, $term)) {
                $frequency += $count;
            }
        }

        return $frequency;
    }
}
