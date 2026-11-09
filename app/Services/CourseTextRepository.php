<?php

namespace App\Services;

use App\Models\ScormCourse;
use Illuminate\Support\Facades\Cache;

/**
 * A course's source text, cleaned and chunked once and kept in the persistent cache. A changed
 * source file is noticed from its size and modification time, so answering a question never
 * re-reads or re-processes it.
 */
class CourseTextRepository
{
    /** Bump when chunking or the cached shape changes, so old cache entries rebuild themselves. */
    private const VERSION = 3;

    /** @var array<string, array{fingerprint: string, text: string, tokens: int, chunks: array}> */
    private array $loaded = [];

    public function __construct(private readonly CourseTextChunker $chunker) {}

    public function hasText(ScormCourse $course): bool
    {
        return $this->sourcePath($course) !== null;
    }

    /** @return list<array{heading: string, text: string, terms: array<string, int>, length: int}> */
    public function chunks(ScormCourse $course): array
    {
        return $this->entry($course)['chunks'] ?? [];
    }

    /** The whole cleaned text, one paragraph per line. */
    public function text(ScormCourse $course): string
    {
        return $this->entry($course)['text'] ?? '';
    }

    /** Estimated model tokens of text(), on the high side. */
    public function tokens(ScormCourse $course): int
    {
        return $this->entry($course)['tokens'] ?? 0;
    }

    /** Re-reads the source and replaces the cache entry. Returns the chunks, empty for a course without text. */
    public function rebuild(ScormCourse $course): array
    {
        unset($this->loaded[$this->key($course)]);
        $path = $this->sourcePath($course);

        if ($path === null) {
            Cache::forget($this->key($course));

            return [];
        }

        $text = $this->chunker->text($path);
        $entry = [
            'fingerprint' => $this->fingerprint($path),
            'text' => $text,
            'tokens' => (int) ceil(mb_strlen($text) / config('scorm.chunks.chars_per_token')),
            'chunks' => $this->chunker->chunk($path),
        ];

        Cache::forever($this->key($course), $entry);
        $this->loaded[$this->key($course)] = $entry;

        return $entry['chunks'];
    }

    private function entry(ScormCourse $course): ?array
    {
        $path = $this->sourcePath($course);

        if ($path === null) {
            return null;
        }

        $key = $this->key($course);
        $fingerprint = $this->fingerprint($path);

        // Several lookups in one request (text, tokens, chunks) share one cache read.
        if (($this->loaded[$key]['fingerprint'] ?? null) === $fingerprint) {
            return $this->loaded[$key];
        }

        $cached = Cache::get($key);

        if (($cached['fingerprint'] ?? null) !== $fingerprint) {
            $this->rebuild($course);

            return $this->loaded[$key];
        }

        return $this->loaded[$key] = $cached;
    }

    private function sourcePath(ScormCourse $course): ?string
    {
        if ($course->number === null) {
            return null;
        }

        $path = config('scorm.texts_path')."/course{$course->number}.txt";

        clearstatcache(true, $path);

        return is_file($path) ? $path : null;
    }

    private function fingerprint(string $path): string
    {
        return implode('|', [self::VERSION, basename($path), filesize($path), filemtime($path)]);
    }

    private function key(ScormCourse $course): string
    {
        return "course-text-chunks:{$course->number}";
    }
}
