<?php

namespace Database\Seeders;

use App\Models\ScormCourse;
use Illuminate\Database\Seeder;

/** Catalogue rows for storage/courses/course{N}. The ids are stable because learner progress in localStorage is keyed by them. */
class BundledCourseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, config('scorm.bundled_course_count')) as $number) {
            $folder = config('scorm.courses_path')."/course{$number}";

            ScormCourse::updateOrCreate(['number' => $number], [
                'id' => substr(md5("course{$number}"), 0, 16),
                'title' => "ԴԱՍ {$number}",
                'description' => 'Articulate Storyline դասընթաց',
                'category' => 'Articulate Storyline',
                // The LMS page reports progress through the app's SCORM API; not every course was published with one.
                'launch_path' => is_file("{$folder}/index_lms.html") ? 'index_lms.html' : 'story.html',
                'is_available' => is_dir($folder),
            ]);
        }
    }
}
