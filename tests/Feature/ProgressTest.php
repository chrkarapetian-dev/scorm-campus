<?php

namespace Tests\Feature;

use App\Models\CourseProgress;
use App\Models\ScormCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private ScormCourse $course;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        $this->learner = User::create(['name' => 'T', 'email' => 'progress@example.com', 'password' => 'password']);
        $this->actingAs($this->learner);
        $this->course = ScormCourse::create(['id' => str_repeat('a', 16), 'title' => 'Դաս', 'description' => 'd', 'category' => 'c', 'launch_path' => 'story.html']);
        $this->url = '/api/scorm/progress/'.$this->course->id;
    }

    public function test_new_user_and_partial_answers_have_no_fabricated_score(): void
    {
        $this->getJson($this->url)->assertOk()->assertJsonPath('completedSections', 0)->assertJsonPath('quiz', null)->assertJsonPath('answers', []);
        $this->postJson($this->url, ['revision' => 0, 'runtime' => [
            'cmi.interactions.0.id' => 'question-1', 'cmi.interactions.0.student_response' => 'A',
            'cmi.interactions.0.result' => 'correct', 'cmi.interactions.0.correct_responses.0.pattern' => 'A',
            'cmi.suspend_data' => '  exact data  ', 'cmi.core.lesson_location' => '',
        ]])->assertOk()->assertJsonPath('quiz', null)->assertJsonPath('answers.0.selected', 'A');
        $this->assertSame('  exact data  ', $this->getJson($this->url)->json('scorm')['cmi.suspend_data']);
        $this->assertDatabaseCount('course_answers', 1);
    }

    public function test_answer_updates_use_stable_question_ids_and_preserve_resume_data(): void
    {
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['cmi.interactions.0.id' => 'q', 'cmi.interactions.0.learner_response' => 'A', 'cmi.suspend_data' => 'resume']])->assertOk();
        $this->postJson($this->url, ['revision' => 1, 'runtime' => ['cmi.interactions.1.id' => 'q', 'cmi.interactions.1.learner_response' => 'B']])->assertOk()->assertJsonPath('answers.0.selected', 'B');
        $this->assertDatabaseCount('course_answers', 1);
        $record = CourseProgress::first();
        $this->assertSame('resume', $record->runtime['cmi.suspend_data']);
        $this->assertSame('B', $record->answers->first()->data['selected']);
    }

    public function test_users_and_courses_are_isolated(): void
    {
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['cmi.interactions.0.id' => 'q', 'cmi.interactions.0.student_response' => 'secret']])->assertOk();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password']);
        $this->actingAs($other)->getJson($this->url)->assertOk()->assertJsonPath('answers', []);
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['cmi.suspend_data' => 'other']])->assertOk();
        $this->actingAs($this->learner)->getJson($this->url)->assertJsonPath('answers.0.selected', 'secret');
        $second = $this->course->replicate();
        $second->id = str_repeat('b', 16);
        $second->save();
        $this->getJson('/api/scorm/progress/'.$second->id)->assertJsonPath('answers', []);
    }

    public function test_reading_different_courses_preserves_answers_and_does_not_modify_progress(): void
    {
        $second = $this->course->replicate();
        $second->id = str_repeat('b', 16);
        $second->save();
        $secondUrl = '/api/scorm/progress/'.$second->id;
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['cmi.interactions.0.id' => 'q', 'cmi.interactions.0.student_response' => 'Առաջին պատասխան']])->assertOk();
        $this->postJson($secondUrl, ['revision' => 0, 'runtime' => ['cmi.interactions.0.id' => 'q', 'cmi.interactions.0.student_response' => 'Երկրորդ պատասխան']])->assertOk();
        $before = CourseProgress::with('answers')->orderBy('id')->get()->toArray();
        foreach (range(1, 3) as $visit) {
            $this->getJson($this->url)->assertOk()->assertJsonPath('answers.0.selected', 'Առաջին պատասխան');
            $this->getJson($secondUrl)->assertOk()->assertJsonPath('answers.0.selected', 'Երկրորդ պատասխան');
        }
        $this->assertSame($before, CourseProgress::with('answers')->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('course_answers', 2);
        $this->course->update(['is_available' => false]);
        $this->getJson($this->url)->assertNotFound();
    }

    public function test_course_selector_contains_only_available_courses_and_handles_an_empty_catalogue(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertSee('id="progress-course-select"', false)
            ->assertViewHas('courses', fn ($courses) => $courses->pluck('id')->all() === [$this->course->id]);
        $this->course->update(['is_available' => false]);
        $this->get('/')->assertOk()->assertSee('Հասանելի դասընթացներ չկան։')
            ->assertViewHas('courses', fn ($courses) => $courses->isEmpty());
    }

    public function test_stale_writes_and_stale_resets_cannot_overwrite_progress(): void
    {
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['cmi.suspend_data' => 'first']])->assertOk();
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['cmi.suspend_data' => 'stale']])->assertStatus(409);
        $this->postJson($this->url, ['revision' => 0, 'reset' => true])->assertStatus(409);
        $this->assertSame('first', CourseProgress::first()->runtime['cmi.suspend_data']);
    }

    public function test_video_ranges_merge_across_visits_without_double_counting(): void
    {
        $this->postJson($this->url, ['revision' => 0, 'video' => ['duration' => 100, 'ranges' => [[0, 50]]]])->assertOk()->assertJsonPath('sections.video.percent', 50);
        $this->postJson($this->url, ['revision' => 1, 'video' => ['duration' => 100, 'ranges' => [[25, 90]]]])->assertOk()->assertJsonPath('sections.video.percent', 90)->assertJsonPath('sections.video.completed', true)->assertJsonPath('completionPercent', 25);
        $this->postJson($this->url, ['revision' => 2, 'video' => ['duration' => 100, 'ranges' => [[80, 20]]]])->assertUnprocessable();
        $this->assertSame(2, CourseProgress::first()->revision);
    }

    public function test_backend_calculates_reading_and_quiz_completion_for_both_scorm_versions(): void
    {
        $this->postJson($this->url, ['revision' => 0, 'slide' => '6m4h0Gy3mUh'])->assertOk();
        $this->postJson($this->url, ['revision' => 1, 'slide' => '6eZpibFOcqO'])->assertJsonPath('sections.materials.completed', true);
        $this->postJson($this->url, ['revision' => 2, 'slide' => '6aymNm6ZUBv', 'runtime' => ['cmi.completion_status' => 'completed', 'cmi.success_status' => 'passed', 'cmi.score.raw' => '8', 'cmi.score.min' => '0', 'cmi.score.max' => '10']])->assertOk()->assertJsonPath('quiz.score', 80)->assertJsonPath('completedSections', 3)->assertJsonPath('completionPercent', 75);
        $this->postJson($this->url, ['revision' => 3, 'reset' => true])->assertOk()->assertJsonPath('answers', [])->assertJsonPath('completedSections', 0);
        $this->postJson($this->url, ['revision' => 4, 'runtime' => ['cmi.core.lesson_status' => 'failed', 'cmi.core.score.raw' => '20']])->assertJsonPath('quiz.score', 20)->assertJsonPath('quiz.passed', false);
    }

    public function test_results_slide_records_failed_attempt_even_when_package_reports_incomplete(): void
    {
        $this->postJson($this->url, ['revision' => 0, 'slide' => '6SfrhhbiYCV', 'runtime' => ['cmi.core.lesson_status' => 'incomplete', 'cmi.core.score.raw' => '30']])
            ->assertOk()->assertJsonPath('quiz.score', 30)->assertJsonPath('quiz.passed', false)->assertJsonPath('sections.quiz.completed', false);
    }

    public function test_validation_authentication_and_foreign_key_cleanup(): void
    {
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['invalid' => 'x']])->assertUnprocessable();
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['cmi.suspend_data' => ['invalid']]])->assertUnprocessable();
        $this->getJson('/api/scorm/progress/missing')->assertNotFound();
        $this->postJson($this->url, ['revision' => 0, 'runtime' => ['cmi.interactions.0.id' => 'q']])->assertOk()->assertJsonPath('answers.0.selected', null);
        $this->course->delete();
        $this->assertDatabaseCount('course_progress', 0);
        $this->assertDatabaseCount('course_answers', 0);
        auth()->forgetGuards();
        $this->getJson($this->url)->assertUnauthorized();
    }
}
