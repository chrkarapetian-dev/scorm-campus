<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\LearningCourse;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;

/** Loads the course material. Safe to re-run: every row is matched on its natural key. */
class LearningCourseSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/learning-course.json')), true, flags: JSON_THROW_ON_ERROR);

        $course = LearningCourse::updateOrCreate(['id' => $data['id']], [
            'title' => $data['title'],
            'short_title' => $data['shortTitle'],
            'language' => $data['language'],
        ]);

        foreach ($data['lessons'] as $position => $lesson) {
            Lesson::updateOrCreate(['id' => $lesson['id']], [
                'learning_course_id' => $course->id,
                'position' => $position,
                'kind' => $lesson['kind'],
                'title' => $lesson['title'],
                'label' => $lesson['label'],
                'lead' => $lesson['lead'],
                'duration' => $lesson['duration'],
                'html' => $lesson['html'],
            ]);
        }

        foreach ($data['goals'] as $goal) {
            Goal::updateOrCreate(['learning_course_id' => $course->id, 'number' => $goal['n']], [
                'title' => $goal['title'],
                'summary' => $goal['short'],
                'group_name' => $goal['group'],
            ]);
        }

        foreach ($data['questions'] as $position => $question) {
            QuizQuestion::updateOrCreate(['learning_course_id' => $course->id, 'position' => $position], [
                'question' => $question['q'],
                'options' => $question['opts'],
                'correct_option' => $question['a'],
                'explanation' => $question['why'],
            ]);
        }
    }
}
