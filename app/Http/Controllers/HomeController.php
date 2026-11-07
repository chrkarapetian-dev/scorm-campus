<?php

namespace App\Http\Controllers;

use App\Models\ScormCourse;
use App\Services\CourseTextRepository;
use App\Services\OpenAiResponses;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(CourseTextRepository $texts, OpenAiResponses $openAi): View
    {
        $courses = ScormCourse::available()->orderBy('number')->latest()->get();
        $sections = config('scorm.storyline_sections');
        $hasAssistant = $courses->mapWithKeys(fn (ScormCourse $course) => [$course->id => $openAi->configured() && $texts->hasText($course)]);

        return view('home', [
            'courses' => $courses,
            'hasAssistant' => $hasAssistant,
            'sections' => $sections,
            'frontend' => [
                'courses' => $courses->map(fn (ScormCourse $course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'url' => $course->launchUrl(),
                    'assistant' => $hasAssistant[$course->id],
                    'openQuestion' => (int) $course->number === config('scorm.open_question_course'),
                ]),
                'sections' => $sections,
            ],
        ]);
    }
}
