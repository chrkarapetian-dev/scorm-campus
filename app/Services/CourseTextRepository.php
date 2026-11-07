<?php

namespace App\Services;

use App\Models\ScormCourse;
use Illuminate\Support\Facades\Cache;

/**
 * Chunks of a course's source text, kept in the persistent cache. A changed source file is
 * noticed from its size and modification time, so answering a question never re-reads it.
 */
class CourseTextRepository
{
    /** Bump when chunking changes, so old cache entries rebuild themselves. */
    private const VERSION = 2;

    public function __construct(private readonly CourseTextChunker $chunker) {}

    public function hasText(ScormCourse $course): bool
    {
        return $this->sourcePath($course) !== null;
    }

    public function chunks(ScormCourse $course): array
    {
        $path = $this->sourcePath($course);

        if ($path === null) {
            return [];
        }

        $cached = Cache::get($this->key($course));

        return ($cached['fingerprint'] ?? null) === $this->fingerprint($path)
            ? $cached['chunks']
            : $this->rebuild($course);
    }

    /** Re-reads the source and replaces the cache entry. Returns the chunks, empty for a course without text. */
    public function rebuild(ScormCourse $course): array
    {
        $path = $this->sourcePath($course);

        if ($path === null) {
            Cache::forget($this->key($course));

            return [];
        }

        $chunks = $this->chunker->chunk($path);

        Cache::forever($this->key($course), ['fingerprint' => $this->fingerprint($path), 'chunks' => $chunks]);

        return $chunks;
    }

    private function sourcePath(ScormCourse $course): ?string
    {
        if ($course->number === null) {
            return null;
        }

        $path = config('scorm.texts_path')."/course{$course->number}.txt";

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
