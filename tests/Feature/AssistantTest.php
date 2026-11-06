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

    private const LONG_ANSWER = 'Կայուն զարգացման ինդիկատորները օգնում են չափել կրթության, եկամտի և կլիմայի փոփոխությունը։ Օրինակ՝ համայնքում կարելի է հետևել ջրի որակի տվյալներին, քանի որ դա ազդում է առողջության վրա։';

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

    public function test_without_a_key_indicator_questions_get_the_built_in_answer_with_a_note(): void
    {
        Http::fake();

        $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'Ի՞նչ է ԻՆԴԻԿԱՏՈՐԸ', 'active_section' => 'video'])
            ->assertOk()
            ->assertJsonPath('citations.0.reference_label', 'Սլայդներ 3–4')
            ->assertJsonPath('notice', 'Նշում․ ցուցադրվում է դասընթացի ներկառուցված պատասխան։ Լայն, ազատ AI պատասխանների համար պետք է միացված լինի OpenAI API բանալին։');

        Http::assertNothingSent();
    }

    public function test_off_topic_questions_are_declined_locally(): void
    {
        $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'Բարև, ինչպե՞ս ես'])
            ->assertOk()
            ->assertJsonPath('citations', [])
            ->assertJsonPath('answer_armenian', 'Ես նախագծված եմ օգնելու միայն այս դասընթացի շրջանակներում։ Խնդրում եմ հարցեր ուղղել կայուն զարգացման դասի նյութի վերաբերյալ։');
    }

    public function test_goal_questions_match_a_goal_by_number_or_title(): void
    {
        $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'Ո՞րն է նպատակ 13-ը'])
            ->assertOk()
            ->assertJsonFragment(['answer_armenian' => 'Ըստ դասընթացի՝ 13-րդ նպատակը՝ «Կլիմայական գործողություններ», ուղղված է հետևյալ խնդրին․ Ձեռնարկել անհապաղ քայլեր կլիմայի փոփոխության և դրա ազդեցությունների դեմ։ Նպատակները փոխկապակցված են, ուստի դրանց առաջընթացը դիտարկվում է հարակից պայմանների հետ։']);
    }

    public function test_general_on_topic_questions_use_the_active_lesson(): void
    {
        $response = $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'Ինչու՞ է կարևոր բնապահպանությունը', 'active_section' => 'video']);

        $response->assertOk()->assertJsonPath('citations.0.source_type', 'video')->assertJsonPath('citations.0.reference_label', '01 · ՏԵՍԱԴԱՍ');
        $this->assertStringContainsString('կայուն զարգացումը չափելի դարձնելը', $response->json('answer_armenian'));
    }

    public function test_with_a_key_the_model_answer_is_returned(): void
    {
        config(['services.openai.key' => 'sk-test', 'services.openai.model' => 'gpt-test']);
        $this->fakeOpenAi(['answer_armenian' => 'AI պատասխան', 'citations' => [], 'suggested_followups_armenian' => []]);

        $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'Ի՞նչ է ինդիկատորը', 'active_section' => 'slides'])
            ->assertOk()
            ->assertJsonPath('answer_armenian', 'AI պատասխան');

        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return $request->hasHeader('Authorization', 'Bearer sk-test')
                && $request['model'] === 'gpt-test'
                && $request['store'] === false
                && $request['text']['format']['name'] === 'armenian_course_answer'
                && $input['student_question'] === 'Ի՞նչ է ինդիկատորը'
                && $input['active_section'] === '02 · ՍԼԱՅԴՆԵՐ'
                && str_contains($input['course_material'], 'Մարդկային զարգացման համաթիվ')
                && ! str_contains($input['course_material'], '<p>');
        });
    }

    public function test_quota_errors_fall_back_to_the_built_in_answer_and_say_why(): void
    {
        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['type' => 'insufficient_quota', 'code' => 'insufficient_quota', 'message' => 'x']], 429)]);

        $response = $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'Ի՞նչ է ինդիկատորը']);

        $response->assertOk()->assertJsonPath('citations.0.reference_label', 'Սլայդներ 3–4');
        $this->assertStringContainsString('API billing', $response->json('notice'));
        $this->assertStringContainsString('ներկառուցված պատասխանը', $response->json('notice'));
    }

    public function test_invalid_key_and_generic_errors_explain_themselves(): void
    {
        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['error' => ['type' => 'invalid_request_error', 'code' => 'invalid_api_key']], 401)
            ->push(['error' => ['type' => 'server_error']], 500)]);

        $this->assertStringContainsString('բանալին անվավեր', $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'ինդիկատոր'])->json('notice'));
        $this->assertStringContainsString('ժամանակավորապես', $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'ինդիկատոր'])->json('notice'));
    }

    public function test_short_answers_are_not_evaluated(): void
    {
        $this->postJson('/api/scorm/evaluate-open-ended', ['student_response' => 'կարճ'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Գրեք առնվազն 40 նիշ՝ հարցին ամբողջական պատասխանելու համար։');
    }

    public function test_local_evaluation_scores_against_the_rubric(): void
    {
        $data = $this->postJson('/api/scorm/evaluate-open-ended', ['student_response' => self::LONG_ANSWER])
            ->assertOk()
            ->assertJsonCount(4, 'criterion_breakdown')
            ->assertJsonPath('max_total_score', 100)
            ->json();

        $points = array_column($data['criterion_breakdown'], 'points_earned', 'criterion_id');

        $this->assertSame(['understanding' => 27, 'connections' => 19, 'example_measure' => 25, 'completeness' => 14], $points);
        $this->assertSame(85, $data['total_score_awarded']);
        $this->assertSame('Իմացող / Լիարժեք', $data['grade_level']);
        $this->assertStringContainsString('տեղային գնահատում', $data['notice']);
    }

    public function test_off_topic_long_answers_score_below_passing(): void
    {
        $data = $this->postJson('/api/scorm/evaluate-open-ended', ['student_response' => str_repeat('Այսօր եղանակը շատ գեղեցիկ է։ ', 3)])->assertOk()->json();

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

        $this->postJson('/api/scorm/evaluate-open-ended', ['student_response' => self::LONG_ANSWER])
            ->assertOk()
            ->assertJsonPath('total_score_awarded', 40)
            ->assertJsonPath('max_total_score', 100)
            ->assertJsonPath('criterion_breakdown.0.points_earned', 30)
            ->assertJsonPath('criterion_breakdown.1.points_earned', 0);
    }

    public function test_a_failed_model_evaluation_falls_back_to_the_rubric(): void
    {
        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['type' => 'server_error']], 500)]);

        $this->postJson('/api/scorm/evaluate-open-ended', ['student_response' => self::LONG_ANSWER])
            ->assertOk()
            ->assertJsonPath('total_score_awarded', 85);
    }

    public function test_assistant_endpoints_are_rate_limited_per_client(): void
    {
        config(['app.debug' => false]);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'ինդիկատոր'])->assertOk();
        }

        $this->postJson('/api/scorm/qa-assistant', ['student_question' => 'ինդիկատոր'])
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
