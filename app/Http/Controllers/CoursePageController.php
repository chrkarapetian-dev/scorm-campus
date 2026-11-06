<?php

namespace App\Http\Controllers;

use App\Models\ScormCourse;
use Symfony\Component\HttpFoundation\Response;

/** Standalone page for a shared /course/{id} link: the package in a frame with a SCORM API. */
class CoursePageController extends Controller
{
    public function __invoke(string $course): Response
    {
        $course = ScormCourse::find($course);

        if ($course === null) {
            return response('Դասընթացը չի գտնվել։', 404)->header('Content-Type', 'text/html; charset=utf-8');
        }

        return response()->view('course', ['course' => $course])->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }
}
