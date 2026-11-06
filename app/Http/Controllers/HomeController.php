<?php

namespace App\Http\Controllers;

use App\Models\ScormCourse;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $courses = ScormCourse::latest()->get();
        $sections = config('scorm.storyline_sections');

        return view('home', [
            'courses' => $courses,
            'sections' => $sections,
            'frontend' => [
                'courses' => $courses->map(fn (ScormCourse $course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'url' => $course->launchUrl(),
                ]),
                'sections' => $sections,
            ],
        ]);
    }
}
