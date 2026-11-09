<?php

namespace Database\Seeders;

use App\Models\ScormCourse;
use Illuminate\Database\Seeder;

/** Catalogue rows for storage/courses/course{N}. The ids are stable because learner progress in localStorage is keyed by them. */
class BundledCourseSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            3 => 'Կայուն զարգացման հայեցակարգի առաջացման կազմակերպչական գործընթացը',
            4 => '«Կայունություն» և «կայուն զարգացում» հասկացությունները',
            5 => 'Կայուն զարգացման հայեցակարգի հիմնական հարցադրումները',
            6 => 'Կայուն զարգացման հայեցակարգի մեթոդաբանական հիմքը․ համակարգային մոտեցում',
            8 => 'Կայուն զարգացման ցուցանիշները (ինդիկատորներ և ինդեքսներ) և նպատակները',
            9 => 'Կայուն զարգացում ապահովելու ռազմավարական անցումները',
            10 => 'Կայուն զարգացում ապահովող սուբյեկտները',
        ];

        foreach (config('scorm.bundled_course_numbers') as $number) {
            $folder = config('scorm.courses_path')."/course{$number}";

            ScormCourse::updateOrCreate(['number' => $number], [
                'id' => substr(md5("course{$number}"), 0, 16),
                'title' => $titles[$number] ?? "ԴԱՍ {$number}",
                'description' => 'Articulate Storyline դասընթաց',
                'category' => 'Articulate Storyline',
                // The LMS page reports progress through the app's SCORM API; not every course was published with one.
                'launch_path' => is_file("{$folder}/index_lms.html") ? 'index_lms.html' : 'story.html',
                'is_available' => is_dir($folder),
            ]);
        }
    }
}
