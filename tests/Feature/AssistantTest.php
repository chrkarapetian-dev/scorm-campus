<?php

namespace Tests\Feature;

use App\Models\AiQuestion;
use App\Models\ScormCourse;
use App\Models\User;
use App\Services\CourseAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\FakesOpenAi;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use FakesOpenAi, RefreshDatabase;

    private const ENDPOINT = '/api/scorm/qa-assistant';

    private const TEXT_8 = "Կայուն զարգացման ցուցանիշները\nՄարդկային զարգացման համաթիվ։ Մարդկային զարգացման համաթիվը համակցված ցուցանիշ է, որը չափում է կյանքի տևողությունը, կրթությունը և եկամուտը։\nԷկոլոգիական ոտնահետքը ցույց է տալիս սպառման համար անհրաժեշտ ռեսուրսները։";

    private const TEXT_6 = "1992 թ. Ռիո դե Ժանեյրոյում կայացած ՄԱԿ-ի համաժողովը ընդունեց կայուն զարգացման հայեցակարգը։\nԳեոհամակարգ տերմինն առաջինն օգտագործել է Վ. Սոչավան 1963 թ.։";

    private User $learner;

    private ScormCourse $course;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/assistant-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->root);
        config(['app.debug' => false, 'services.openai.key' => 'test-key', 'services.openai.question_limit' => 10, 'scorm.texts_path' => $this->root]);
        $this->learner = User::create(['name' => 'T', 'email' => 't@example.com', 'password' => 'password']);
        $this->actingAs($this->learner);
        $this->course = $this->course(8, self::TEXT_8);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function course(int $number, string $text): ScormCourse
    {
        file_put_contents($this->root."/course$number.txt", $text);

        return ScormCourse::create(['id' => substr(md5("assistant-$number"), 0, 16), 'number' => $number, 'title' => "Դաս $number", 'description' => 'd', 'category' => 'c', 'launch_path' => 'story.html']);
    }

    private function ask(string $question, ?ScormCourse $course = null)
    {
        return $this->postJson(self::ENDPOINT, ['course_id' => ($course ?? $this->course)->id, 'student_question' => $question]);
    }

    private function answered(string $answer = 'Համաթիվը չափում է կյանքի տևողությունը, կրթությունը և եկամուտը։'): array
    {
        return ['answer' => $answer, 'found' => true];
    }

    public function test_question_is_required_and_bounded(): void
    {
        $this->postJson(self::ENDPOINT, ['student_question' => '   '])->assertStatus(400)->assertJsonPath('error', 'Գրեք ձեր հարցը։');
        $this->call('POST', self::ENDPOINT, [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json')->assertStatus(400);
        $this->ask(str_repeat('ա', 101))->assertStatus(422);
        $this->postJson(self::ENDPOINT, ['course_id' => str_repeat('f', 16), 'student_question' => 'Ինչ է համաթիվը'])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_short_answers_are_not_evaluated(): void
    {
        $this->postJson('/api/scorm/evaluate-open-ended', ['student_response' => str_repeat('ա', 199)])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Գրեք առնվազն 200 նիշ՝ հարցին ամբողջական պատասխանելու համար։');
    }

    public function test_the_whole_course_text_is_sent_as_a_cached_prefix_and_the_question_separately(): void
    {
        $this->fakeOpenAi(['course_answer' => [$this->answered(), $this->answered('Ոտնահետքը ցույց է տալիս ռեսուրսները։')]]);

        $this->ask('Ինչ է HDI-ն')->assertOk()->assertJsonPath('questions_remaining', 9);
        $this->ask('Ինչ է էկոլոգիական հետքը')->assertOk()->assertJsonPath('answer', 'Ոտնահետքը ցույց է տալիս ռեսուրսները։');

        [[$first], [$second]] = Http::recorded();
        $this->assertSame(['course_title' => 'Դաս 8', 'course_material' => [['heading' => 'Դաս 8', 'text' => self::TEXT_8]]], $this->sentContext($first));
        $this->assertSame(['student_question' => 'Ինչ է HDI-ն'], $this->sentInput($first));
        // A different question leaves the cached prefix byte-identical.
        $this->assertSame($first['input'][0], $second['input'][0]);
        $this->assertSame(['mode' => 'explicit'], $first['input'][0]['content'][1]['prompt_cache_breakpoint']);
        $this->assertSame('low', $first['reasoning']['effort']);
        $this->assertSame('gpt-6-luna', $first['model']);
        $this->assertFalse($first['store']);
        $this->assertTrue($first['text']['format']['strict']);

        $this->assertDatabaseHas('ai_questions', ['question' => 'Ինչ է HDI-ն', 'found' => true, 'input_tokens' => 100, 'cached_input_tokens' => 60, 'output_tokens' => 50]);
    }

    public function test_each_course_sends_only_its_own_text(): void
    {
        $other = $this->course(6, self::TEXT_6);
        $this->fakeOpenAi(['course_answer' => [$this->answered(), $this->answered('1992 թ.')]]);

        $this->ask('Ինչ է համաթիվը')->assertOk();
        $this->ask('Երբ է կայացել Ռիոյի համաժողովը', $other)->assertOk()->assertJsonPath('answer', '1992 թ.');

        [[$first], [$second]] = Http::recorded();
        $this->assertSame(self::TEXT_8, $this->sentContext($first)['course_material'][0]['text']);
        $this->assertSame(self::TEXT_6, $this->sentContext($second)['course_material'][0]['text']);
        $this->assertDatabaseHas('ai_questions', ['course_id' => $other->id, 'answer' => '1992 թ.']);
    }

    public function test_an_oversized_text_falls_back_to_keyword_excerpts(): void
    {
        config(['scorm.context.full_text_max_tokens' => 10]);
        $this->fakeOpenAi(['course_answer' => [$this->answered()]]);

        $this->ask('Ինչ է մարդկային զարգացման համաթիվը')->assertOk();
        $material = $this->sentContext(Http::recorded()[0][0])['course_material'];
        $this->assertStringContainsString('Մարդկային զարգացման համաթիվը', json_encode($material, JSON_UNESCAPED_UNICODE));

        // Nothing in the text matches, so no model call is made and nothing is counted.
        $this->ask('Ով է Մեսսին')->assertOk()->assertJsonPath('answer', CourseAssistant::NOT_FOUND_ANSWER)->assertJsonPath('questions_remaining', 9);
        Http::assertSentCount(1);
    }

    public function test_an_answer_the_course_does_not_contain_is_logged_but_not_counted(): void
    {
        $this->fakeOpenAi(['course_answer' => [['answer' => 'Դասընթացի նյութում այս մասին տեղեկություն չկա։', 'found' => false]]]);

        $this->ask('Որն է Հայաստանի մայրաքաղաքը')->assertOk()
            ->assertJsonPath('answer', CourseAssistant::NOT_FOUND_ANSWER)
            ->assertJsonPath('questions_remaining', 10);

        $this->assertSame(0, $this->learner->fresh()->ai_questions_used);
        $this->assertDatabaseHas('ai_questions', ['question' => 'Որն է Հայաստանի մայրաքաղաքը', 'found' => false]);
    }

    public function test_the_tenth_question_is_the_last_and_further_ones_make_no_api_call(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->fakeOpenAi(['course_answer' => array_fill(0, 10, $this->answered())]);

        for ($i = 1; $i <= 10; $i++) {
            $this->ask("Ինչ է համաթիվը $i")->assertOk()->assertJsonPath('questions_remaining', 10 - $i);
        }

        $this->ask('Ինչ է համաթիվը 11')->assertForbidden()->assertJsonPath('questions_remaining', 0);
        Http::assertSentCount(10);
        $this->assertSame(10, $this->learner->fresh()->ai_questions_used);
        $this->assertSame(10, AiQuestion::where('found', true)->count());
    }

    public function test_a_parallel_request_cannot_use_a_question_that_is_already_reserved(): void
    {
        $this->learner->forceFill(['ai_questions_used' => 9])->save();
        $nested = false;
        Http::fake(function () use (&$nested) {
            if (! $nested) {
                $nested = true;
                $this->ask('Ինչ է ոտնահետքը')->assertForbidden();
            }

            return Http::response($this->envelope($this->answered()));
        });

        $this->ask('Ինչ է համաթիվը')->assertOk()->assertJsonPath('questions_remaining', 0);
        Http::assertSentCount(1);
        $this->assertSame(10, $this->learner->fresh()->ai_questions_used);
    }

    public function test_api_errors_timeouts_and_a_missing_key_never_use_a_question(): void
    {
        $error = fn () => Http::response(['error' => ['code' => 'server_error', 'message' => 'boom']], 500);
        $this->fakeOpenAi(['course_answer' => [
            $error, $error, // the request and its quick retry
            fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
            fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
            fn () => Http::response(['error' => ['code' => 'insufficient_quota', 'message' => 'quota']], 429),
            fn () => Http::response($this->envelope($this->answered(''))),
            $error, $this->answered(), // a single transient error is retried and succeeds
        ]]);

        for ($i = 0; $i < 4; $i++) {
            $this->ask('Ինչ է համաթիվը')->assertStatus(503)
                ->assertJsonPath('error', 'AI ծառայությունը ժամանակավորապես անհասանելի է։ Փորձեք կրկին․ այս հարցը չի հաշվվել։')
                ->assertJsonPath('questions_remaining', 10);
        }
        // The last question succeeding proves every queued reply was used in order, retries included.
        // Http::recorded() leaves out the two connection failures, so 6 of the 8 calls are listed.
        $this->ask('Ինչ է համաթիվը')->assertOk()->assertJsonPath('questions_remaining', 9);
        Http::assertSentCount(6);

        config(['services.openai.key' => null]);
        $this->ask('Ինչ է համաթիվը')->assertStatus(503)->assertJsonPath('questions_remaining', 9);
        Http::assertSentCount(6);
        $this->assertSame(1, AiQuestion::count());
    }

    public function test_malformed_answer_fields_are_rejected_without_using_a_question(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $invalid = [
            ['answer' => 'Պատասխան առանց found դաշտի։'],
            ['answer' => 'Պատասխան։', 'found' => 'false'],
            ['answer' => 'Պատասխան։', 'found' => 0],
            ['answer' => 'Պատասխան։', 'found' => null],
            ['answer' => 123, 'found' => true],
            ['answer' => ['text' => 'Պատասխան։'], 'found' => true],
        ];
        $this->fakeOpenAi(['course_answer' => $invalid]);

        foreach ($invalid as $_) {
            $this->ask('Ինչ է համաթիվը')->assertStatus(503)->assertJsonPath('questions_remaining', 10);
        }

        $this->assertDatabaseCount('ai_questions', 0);
        $this->assertSame(0, $this->learner->fresh()->ai_questions_used);
        Http::assertSentCount(count($invalid));
    }

    public function test_assistant_endpoints_are_rate_limited_per_client(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson(self::ENDPOINT, ['student_question' => ' '])->assertStatus(400);
        }

        $this->postJson(self::ENDPOINT, ['student_question' => ' '])
            ->assertStatus(429)
            ->assertJsonPath('error', 'Հարցումների սահմանաչափը լրացել է։ Սպասեք և փորձեք կրկին ավելի ուշ։');
    }

    public function test_unexpected_errors_release_the_question_and_hide_details_outside_debug_mode(): void
    {
        $this->app->bind(CourseAssistant::class, fn () => new class extends CourseAssistant
        {
            public function __construct() {}

            public function answer(ScormCourse $course, string $question, array $material): array
            {
                throw new RuntimeException('secret detail');
            }
        });

        $this->ask('Ինչ է համաթիվը')->assertStatus(500)->assertExactJson(['error' => 'Հարցումը հնարավոր չեղավ մշակել։']);
        $this->assertSame(0, $this->learner->fresh()->ai_questions_used);
    }
}
