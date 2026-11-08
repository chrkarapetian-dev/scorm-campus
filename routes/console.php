<?php

use App\Models\ScormCourse;
use App\Services\CourseTextRepository;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('courses:rebuild-cache', function (CourseTextRepository $texts) {
    foreach (ScormCourse::whereNotNull('number')->orderBy('number')->get() as $course) {
        $chunks = $texts->rebuild($course);

        $this->line("{$course->title}: ".($chunks === [] ? 'no source text' : count($chunks).' chunks'));
    }
})->purpose('Re-read the course text files and rebuild their cached chunks');

Artisan::command('courses:warm-cache', function (CourseTextRepository $texts) {
    foreach (ScormCourse::whereNotNull('number')->orderBy('number')->get() as $course) {
        $chunks = $texts->chunks($course);
        $this->line("{$course->title}: ".($chunks === [] ? 'no source text' : count($chunks).' chunks'));
    }
})->purpose('Build missing or stale course text cache entries');
