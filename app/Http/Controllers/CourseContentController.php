<?php

namespace App\Http\Controllers;

use App\Models\ScormCourse;
use App\Services\ScormPackageImporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Serves course files to logged-in users. Range requests matter here: Storyline's video player seeks with them. */
class CourseContentController extends Controller
{
    public function __invoke(string $course, string $path): Response
    {
        return $this->serve(ScormCourse::available()->find($course), $path);
    }

    public function bundled(string $number, string $path): Response
    {
        return $this->serve(ScormCourse::available()->where('number', $number)->first(), $path);
    }

    private function serve(?ScormCourse $course, string $path): Response
    {
        if ($course === null || ! ScormPackageImporter::isSafePath($path)) {
            return $this->notFound();
        }

        $root = realpath($course->storagePath());
        $file = realpath($course->storagePath().DIRECTORY_SEPARATOR.$path);

        if ($root === false || $file === false || ! is_file($file) || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
            return $this->notFound();
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $headers = [
            'Content-Type' => config("scorm.mime_types.{$extension}", 'application/octet-stream'),
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
            // A launch page must not outlive the session. Assets are stored only in this user's browser.
            'Cache-Control' => in_array($extension, ['html', 'htm'], true) ? 'no-store' : 'private, max-age=3600',
        ];

        // Nginx sends the file itself, so a long video doesn't hold a PHP worker.
        if ($course->number !== null && filled($prefix = config('scorm.x_accel_prefix'))) {
            $relative = "course{$course->number}/".implode('/', array_map('rawurlencode', explode('/', $path)));

            return response('', 200, $headers + ['X-Accel-Redirect' => $prefix.$relative]);
        }

        return new BinaryFileResponse($file, 200, $headers, public: false, autoLastModified: false);
    }

    private function notFound(): Response
    {
        return response('Չի գտնվել։', 404)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
