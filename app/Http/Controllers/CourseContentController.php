<?php

namespace App\Http\Controllers;

use App\Models\ScormCourse;
use App\Services\ScormPackageImporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Serves unpacked package files. Range requests matter here: Storyline's video player seeks with them. */
class CourseContentController extends Controller
{
    public function __invoke(string $course, string $path): Response
    {
        if (! ScormPackageImporter::isSafePath($path) || ! ScormCourse::whereKey($course)->exists()) {
            return $this->notFound();
        }

        $root = config('scorm.storage_path').DIRECTORY_SEPARATOR.$course;
        $file = realpath($root.DIRECTORY_SEPARATOR.$path);

        if ($file === false || ! is_file($file) || ! str_starts_with($file, realpath($root).DIRECTORY_SEPARATOR)) {
            return $this->notFound();
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return new BinaryFileResponse($file, 200, [
            'Content-Type' => config("scorm.mime_types.{$extension}", 'application/octet-stream'),
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-store',
        ], public: false, autoLastModified: false);
    }

    private function notFound(): Response
    {
        return response('Չի գտնվել։', 404)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
