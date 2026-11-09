<?php

namespace App\Services;

/** Turns a course's .txt source into chunks that keep whole paragraphs and sentences together. */
class CourseTextChunker
{
    /** Armenian "։" and, as these texts often use it, ":" end sentences alongside the usual marks. */
    private const SENTENCE_END = '[։:.?!…]';

    /** The whole source as cleaned paragraphs, one per line: what the model reads when the course fits its budget. */
    public function text(string $path): string
    {
        return implode("\n", array_column($this->paragraphs($path), 0));
    }

    /** @return list<array{heading: string, text: string, terms: array<string, int>, length: int}> */
    public function chunk(string $path): array
    {
        $maxChars = (int) (config('scorm.chunks.max_tokens') * config('scorm.chunks.chars_per_token'));
        $overlapChars = (int) (config('scorm.chunks.overlap_tokens') * config('scorm.chunks.chars_per_token'));

        $chunks = [];
        $units = [];
        $size = 0;
        $heading = '';
        $chunkHeading = '';

        $startsChunk = true;

        $flush = function () use (&$chunks, &$units, &$size, &$chunkHeading, &$startsChunk) {
            if ($units !== []) {
                $chunks[] = ['heading' => $chunkHeading, 'text' => implode("\n", $units)];
            }

            $units = [];
            $size = 0;
            $startsChunk = true;
        };

        foreach ($this->paragraphs($path) as [$text, $isHeading]) {
            $sectionTitle = $isHeading ? $text : $this->runInHeading($text);

            // A new section starts a new chunk, unless that would leave the current one very small.
            if ($sectionTitle !== null) {
                if ($size >= $maxChars * 0.4) {
                    $flush();
                }

                $heading = $sectionTitle;
            }

            foreach ($this->pieces($text, $maxChars) as $piece) {
                $length = mb_strlen($piece);

                if ($units !== [] && $size + $length > $maxChars) {
                    $overlap = $this->tail(end($units), $overlapChars);
                    $flush();

                    if ($overlap !== '') {
                        $units[] = $overlap;
                        $size = mb_strlen($overlap);
                    }
                }

                if ($startsChunk) {
                    $chunkHeading = $heading;
                    $startsChunk = false;
                }

                $units[] = $piece;
                $size += $length;
            }
        }

        $flush();

        // A small fragment reads better as the end of the previous chunk than on its own.
        $merged = [];

        foreach ($chunks as $chunk) {
            $last = array_key_last($merged);

            if ($last !== null && min(mb_strlen($chunk['text']), mb_strlen($merged[$last]['text'])) < $maxChars * 0.2) {
                $merged[array_key_last($merged)]['text'] .= "\n".$chunk['text'];
            } else {
                $merged[] = $chunk;
            }
        }

        return array_map(function (array $chunk) {
            $terms = CourseChunkSearch::terms($chunk['heading'].' '.$chunk['text']);

            return $chunk + ['terms' => array_count_values($terms), 'length' => count($terms)];
        }, $merged);
    }

    /** @return list<array{0: string, 1: bool}> each line's text and whether it is a heading */
    private function paragraphs(string $path): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));
        $paragraphs = [];

        foreach (preg_split('/\R+/u', $content) ?: [] as $line) {
            $line = $this->clean($line);

            if ($line !== '') {
                $paragraphs[] = [$line, $this->looksLikeHeading($line)];
            }
        }

        return $paragraphs;
    }

    private function looksLikeHeading(string $text): bool
    {
        return mb_strlen($text) >= 3 && mb_strlen($text) <= 80
            && ! $this->isLink($text)
            && ! preg_match('/'.self::SENTENCE_END.'$/u', $text);
    }

    /** Many sections open inline: "Ինդեքսներ։ Ինդեքսը համակցված ցուցանիշ է…". */
    private function runInHeading(string $text): ?string
    {
        return ! $this->isLink($text) && preg_match('/^(.{3,70}?)[։:]\s+\S/u', $text, $match) ? $match[1] : null;
    }

    private function isLink(string $text): bool
    {
        return (bool) preg_match('~^(https?://|www\.)~i', $text);
    }

    /** @return list<string> the paragraph as it is, or split at sentence ends if it can't fit one chunk */
    private function pieces(string $text, int $maxChars): array
    {
        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $pieces = [];
        $current = '';

        foreach ($this->sentences($text) as $sentence) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > $maxChars) {
                $pieces[] = $current;
                $current = '';
            }

            // Only a single sentence longer than a whole chunk gets cut, and then at a word boundary.
            while (mb_strlen($sentence) > $maxChars) {
                $cut = mb_strrpos(mb_substr($sentence, 0, $maxChars), ' ') ?: $maxChars;
                $pieces[] = trim(mb_substr($sentence, 0, $cut));
                $sentence = trim(mb_substr($sentence, $cut));
            }

            $current = trim($current.' '.$sentence);
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        return preg_split('/(?<='.self::SENTENCE_END.')\s+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [$text];
    }

    /** Whole trailing sentences for the next chunk's overlap: never part of a sentence, and empty if the last one is too long. */
    private function tail(string $text, int $maxChars): string
    {
        $tail = '';

        foreach (array_reverse($this->sentences($text)) as $sentence) {
            $candidate = trim($sentence.' '.$tail);

            if (mb_strlen($candidate) > $maxChars) {
                break;
            }

            $tail = $candidate;
        }

        return $tail === $text ? '' : $tail;
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }
}
