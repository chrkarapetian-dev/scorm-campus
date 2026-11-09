<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CourseAssistant;
use Database\Seeders\LearningCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private const LONG_ANSWER = 'Կայուն զարգացման ինդիկատորները օգնում են չափել կրթության, եկամտի և կլիմայի փոփոխությունը։ Օրինակ՝ համայնքում կարելի է հետևել ջրի որակի տվյալներին, քանի որ դա ազդում է առողջության վրա։ Դա կարևոր է բոլորի համար։';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LearningCourseSeeder::class);
        config(['services.openai.key' => null, 'services.openai.question_limit' => 1000]);
        $this->actingAs(User::create(['name' => 'T', 'email' => 't@example.com', 'password' => 'password']));
    }

    private function fakeOpenAi(array $json): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($json)]]]],
        ])]);
    }

    public function test_question_is_required(): void
    {
        $this->postJson('/api/scorm/qa-assistant', ['student_question' => '   '])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Գրեք ձեր հարցը։');

        $this->call('POST', '/api/scorm/qa-assistant', [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json')
            ->assertStatus(400);
    }

    public function test_short_answers_are_not_evaluated(): void
    {
        $this->postJson('/api/scorm/evaluate-open-ended', ['student_response' => str_repeat('ա', 199)])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Գրեք առնվազն 200 նիշ՝ հարցին ամբողջական պատասխանելու համար։');
    }

    public function test_local_evaluation_scores_against_the_rubric(): void
    {
        $data = app(CourseAssistant::class)->evaluate(self::LONG_ANSWER);

        $points = array_column($data['criterion_breakdown'], 'points_earned', 'criterion_id');

        $this->assertSame(['understanding' => 27, 'connections' => 19, 'example_measure' => 25, 'completeness' => 14], $points);
        $this->assertSame(85, $data['total_score_awarded']);
        $this->assertSame('Իմացող / Լիարժեք', $data['grade_level']);
        $this->assertStringContainsString('տեղային գնահատում', $data['notice']);
    }

    public function test_off_topic_long_answers_score_below_passing(): void
    {
        $data = app(CourseAssistant::class)->evaluate(str_repeat('Այսօր եղանակը շատ գեղեցիկ է։ ', 8));

        $this->assertLessThan(60, $data['total_score_awarded']);
        $this->assertSame('Անբավարար', $data['grade_level']);
    }

    public function test_model_scores_are_clamped_to_the_rubric_and_resummed(): void
    {
        config(['services.openai.key' => 'sk-test']);
        $this->fakeOpenAi([
            'total_score_awarded' => 500,
            'max_total_score' => 500,
            'summary_feedback_armenian' => 'լավ',
            'actionable_tips_armenian' => 'շարունակեք',
            'criterion_breakdown' => [
                ['criterion_id' => 'understanding', 'points_earned' => 99, 'max_points' => 99, 'reasoning_armenian' => 'a'],
                ['criterion_id' => 'connections', 'points_earned' => -5, 'max_points' => 25, 'reasoning_armenian' => 'b'],
                ['criterion_id' => 'example_measure', 'points_earned' => 10, 'max_points' => 25, 'reasoning_armenian' => 'c'],
            ],
        ]);

        $result = app(CourseAssistant::class)->evaluate(self::LONG_ANSWER);
        $this->assertSame(40.0, $result['total_score_awarded']);
        $this->assertSame(100, $result['max_total_score']);
        $this->assertSame(30, $result['criterion_breakdown'][0]['points_earned']);
        $this->assertSame(0, $result['criterion_breakdown'][1]['points_earned']);
    }

    public function test_a_failed_model_evaluation_falls_back_to_the_rubric(): void
    {
        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['type' => 'server_error']], 500)]);

        $result = app(CourseAssistant::class)->evaluate(self::LONG_ANSWER);
        $this->assertSame(85, $result['total_score_awarded']);
        $this->assertStringContainsString('տեղային գնահատում', $result['notice']);
    }

    public function test_assistant_endpoints_are_rate_limited_per_client(): void
    {
        config(['app.debug' => false]);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/scorm/qa-assistant', ['student_question' => ' '])->assertStatus(400);
        }

        $this->postJson('/api/scorm/qa-assistant', ['student_question' => ' '])
            ->assertStatus(429)
            ->assertJsonPath('error', 'Հարցումների սահմանաչափը լրացել է։ Սպասեք մեկ րոպե և փորձեք կրկին։');
    }

    public function test_unexpected_api_errors_hide_details_outside_debug_mode(): void
    {
        config(['app.debug' => false]);
        $this->app->bind(CourseAssistant::class, fn () => throw new RuntimeException('secret detail'));

        $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'ինդիկատոր'])
            ->assertStatus(500)
            ->assertExactJson(['error' => 'Հարցումը հնարավոր չեղավ մշակել։']);
    }
}
