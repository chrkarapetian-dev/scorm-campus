<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LearningCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningCourseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LearningCourseSeeder::class);
        $this->actingAs(User::create(['name' => 'T', 'email' => 't@example.com', 'password' => 'password']));
    }

    public function test_course_material_is_seeded_completely_and_reseeding_is_safe(): void
    {
        $this->seed(LearningCourseSeeder::class);

        $this->assertDatabaseCount('learning_courses', 1);
        $this->assertDatabaseCount('lessons', 5);
        $this->assertDatabaseCount('goals', 17);
        $this->assertDatabaseCount('quiz_questions', 5);
    }

    public function test_learning_course_endpoint_exposes_lessons_and_goals_without_lesson_html(): void
    {
        $response = $this->getJson('/api/learning-course')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('id', 'topic-8-sustainable-development')
            ->assertJsonCount(5, 'lessons')
            ->assertJsonCount(17, 'goals')
            ->assertJsonPath('lessons.0.id', 'video')
            ->assertJsonPath('goals.12.n', 13)
            ->assertJsonPath('goals.12.title', 'Կլիմայական գործողություններ')
            ->assertJsonPath('goals.0.group', 'Սոցիալական հիմքեր');

        $this->assertArrayNotHasKey('html', $response->json('lessons.0'));
    }

    public function test_recommendations_reflect_progress(): void
    {
        $this->postJson('/api/recommendations', [])
            ->assertOk()
            ->assertJsonPath('curated_rows.0.items.0.badge_text_armenian', 'Նոր դասընթաց')
            ->assertJsonPath('curated_rows.0.items.0.progress_percentage', 0)
            ->assertJsonPath('curated_rows.1.items.0.badge_text_armenian', 'Կրկնել և ամրապնդել')
            ->assertJsonPath('curated_rows.2.items.0.badge_text_armenian', 'Հայերեն · 5 բաժին');

        $this->postJson('/api/recommendations', ['progress' => [
            'completed' => ['video', 'slides', 'literature', 'quiz', 'reflection'],
            'seconds' => ['video' => 600, 'slides' => 600],
            'quiz' => ['correct' => 5, 'total' => 5],
        ]])
            ->assertOk()
            ->assertJsonPath('curated_rows.0.items', [])
            ->assertJsonPath('curated_rows.1.items', [])
            ->assertJsonPath('curated_rows.2.items.0.progress_percentage', 100)
            ->assertJsonPath('curated_rows.2.items.0.journey_summary_armenian.total_time_formatted', '20ր')
            ->assertJsonPath('curated_rows.2.items.0.journey_summary_armenian.quiz_accuracy_percentage', 100);
    }

    public function test_unknown_routes_answer_in_armenian(): void
    {
        $this->getJson('/api/nope')->assertNotFound()->assertJsonPath('error', 'Այս հասցեն հասանելի չէ։');
        $this->deleteJson('/api/courses')->assertNotFound()->assertJsonPath('error', 'Այս հասցեն հասանելի չէ։');
        $this->get('/nope')->assertNotFound()->assertSee('Չի գտնվել։');
    }
}
