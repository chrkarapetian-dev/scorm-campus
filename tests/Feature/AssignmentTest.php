<?php

namespace Tests\Feature;

use App\Models\AssignmentSubmission;
use App\Models\ScormCourse;
use App\Models\User;
use App\Services\CourseAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\FakesOpenAi;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use FakesOpenAi, RefreshDatabase;

    private User $learner;

    private ScormCourse $course;

    private string $root;

    private const ENDPOINT = '/api/scorm/evaluate-open-ended';

    private const ANSWER = 'Կայուն զարգացման ինդիկատորները օգնում են գնահատել համայնքի փոփոխությունները։ Ջրի որակը և կրթության հասանելիությունը կարևոր են մարդկանց կյանքի համար։ Օրինակ՝ ջրի որակի փոփոխությունը համեմատում ենք նախորդ տարվա հետ և հիմնավորում մեր եզրակացությունը։';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->root = sys_get_temp_dir().'/assignment-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->root);
        config(['app.debug' => false, 'services.openai.key' => 'test-key', 'scorm.texts_path' => $this->root]);
        $this->learner = User::create(['name' => 'Սովորող', 'email' => 'assignment@example.com', 'password' => 'password']);
        $this->actingAs($this->learner);
        $this->course = $this->course(8);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function course(int $number): ScormCourse
    {
        file_put_contents($this->root."/course$number.txt", $number === 8
            ? 'Կայուն զարգացման ինդիկատորներն ու համաթվերը չափում են մարդկանց կյանքի, տնտեսության և շրջակա միջավայրի փոփոխությունները։ Ջրի որակը չափելի տվյալ է։'
            : 'Համակարգային մոտեցումը կայուն զարգացման մեթոդաբանական հիմքն է։ Համակարգի տարրերը փոխկապակցված են։');

        return ScormCourse::create(['id' => substr(md5("fixture-$number"), 0, 16), 'number' => $number, 'title' => "Դաս $number", 'description' => 'd', 'category' => 'c', 'launch_path' => 'story.html']);
    }

    private function detection(int $probability = 20): array
    {
        return [
            'ai_probability' => $probability, 'reason' => 'Սա մոտավոր գնահատում է՝ հիմնված տեքստի կրկնվող ձևակերպումների վրա։',
            'indicators' => [
                ['category' => 'formulaic_structure', 'quote' => 'Կայուն զարգացման', 'explanation' => 'Կաղապարային կառուցվածք։'],
                ['category' => 'generic_exposition', 'quote' => 'Օրինակ՝', 'explanation' => 'Ընդհանուր ձևակերպում։'],
            ],
            'counter_indicators' => ['Ձևակերպումները կարող են գրված լինել սովորողի կողմից։'],
        ];
    }

    private function grade(): array
    {
        return ['material_sufficient' => true, 'summary' => 'Հիմնական գաղափարները ճիշտ են, որոշ մանրամասներ բացակայում են։',
            'criteria' => [
                'accuracy' => ['points' => 30, 'reason' => 'Ճշգրիտ է։'],
                'understanding' => ['points' => 25, 'reason' => 'Կապերը բացատրված են։'],
                'completeness' => ['points' => 20, 'reason' => 'Կան բացթողումներ։'],
                'clarity' => ['points' => 10, 'reason' => 'Հստակ է։'],
            ],
            'correct_points' => ['Չափելի օրինակը ճիշտ է։'], 'missing_points' => ['Տվյալների աղբյուրը։'], 'improvements' => ['Նշեք տվյալների աղբյուրը։']];
    }

    /** One detection and one grading reply per submission; both are always requested together. */
    private function fake(int $probability = 20, ?array $grade = null, int $times = 1): void
    {
        $this->fakeOpenAi([
            'assignment_detection' => array_fill(0, $times, $this->detection($probability)),
            'assignment_grading' => array_fill(0, $times, $grade ?? $this->grade()),
        ]);
    }

    private function submit(array $extra = [])
    {
        return $this->postJson(self::ENDPOINT, $extra + ['course_id' => $this->course->id, 'student_response' => self::ANSWER]);
    }

    public function test_empty_short_nonstring_and_oversized_answers_are_rejected_without_api_calls(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['', str_repeat("\u{200B}\u{00A0}", 100), str_repeat('ա', 199), ['bad'], str_repeat('ա', 6001)] as $answer) {
            $this->submit(['student_response' => $answer])->assertUnprocessable();
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_padding_that_only_meets_the_length_rule_is_refused_without_api_calls(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach ([str_repeat('ա', 200), str_repeat('բառ ', 60), str_repeat('Կայուն զարգացում։ ', 15), str_repeat('1234567890 ', 25)] as $answer) {
            $this->submit(['student_response' => $answer])->assertUnprocessable()->assertJsonPath('error', __('assignment.meaningless'));
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_exactly_200_unicode_characters_are_accepted_and_saved(): void
    {
        $answer = mb_substr(self::ANSWER, 0, 200);
        $this->assertSame(200, mb_strlen($answer));
        $detection = $this->detection();
        $detection['indicators'] = [];
        $this->fakeOpenAi(['assignment_detection' => [$detection], 'assignment_grading' => [$this->grade()]]);
        $this->submit(['student_response' => "  $answer \u{200B} "])->assertOk()->assertJsonPath('final_score', 85);
        $this->assertDatabaseHas('assignment_submissions', ['answer' => $answer, 'user_id' => $this->learner->id, 'status' => 'evaluated', 'final_score' => 85]);
    }

    public static function thresholds(): array
    {
        return [[0, 'likely_human_written', 85], [29, 'likely_human_written', 85], [30, 'suspicious', 85], [35, 'suspicious', 85], [49, 'suspicious', 85], [50, 'potentially_ai_generated', 0], [65, 'potentially_ai_generated', 0], [69, 'potentially_ai_generated', 0], [70, 'strong_ai_indicators', 0], [100, 'strong_ai_indicators', 0]];
    }

    #[DataProvider('thresholds')]
    public function test_server_enforces_all_detection_boundaries(int $probability, string $classification, int $score): void
    {
        $this->fake($probability);
        $response = $this->submit(['final_score' => 100, 'evaluation_score' => 100, 'ai_probability' => 0, 'status' => 'evaluated', 'assignment_question' => 'forged']);
        $response->assertOk()->assertJsonPath('final_score', $score)->assertJsonPath('classification', $classification)
            ->assertJsonPath('ai_probability', $probability)->assertJsonPath('assignment_question', CourseAssistant::OPEN_QUESTION);
        Http::assertSentCount(2);
        $row = AssignmentSubmission::first();
        $this->assertSame($score, $row->final_score);
        // Both parallel calls are paid for, so both count even when the grade is discarded.
        $this->assertSame([200, 120, 100], [$row->input_tokens, $row->cached_input_tokens, $row->output_tokens]);
        if ($score === 0) {
            $response->assertJsonPath('feedback', null)->assertJsonPath('status', 'flagged')->assertJsonMissingPath('evaluation_score');
            $this->assertNull($row->evaluation_score);
        }
    }

    public function test_threshold_is_configurable(): void
    {
        config(['scorm.assignments.ai_threshold' => 70]);
        $this->fake(65);
        $this->submit()->assertOk()->assertJsonPath('final_score', 85)->assertJsonPath('ai_threshold', 70)->assertJsonPath('status', 'evaluated');
        Http::assertSentCount(2);
    }

    public function test_user_can_only_read_own_completed_results_and_history(): void
    {
        $this->fake();
        $id = $this->submit()->assertOk()->json('id');
        $this->getJson("/api/scorm/assignment-submissions/$id")->assertOk()->assertJsonPath('id', $id);
        $this->getJson('/api/scorm/assignment-submissions?course_id='.$this->course->id)->assertOk()->assertJsonCount(1, 'submissions');
        $this->actingAs(User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password']));
        $this->getJson("/api/scorm/assignment-submissions/$id")->assertNotFound();
        $this->getJson('/api/scorm/assignment-submissions?course_id='.$this->course->id)->assertOk()->assertJsonCount(0, 'submissions');
        $this->patchJson("/api/scorm/assignment-submissions/$id", ['final_score' => 100])->assertNotFound();
    }

    public function test_assignment_and_history_require_login(): void
    {
        auth()->logout();
        $this->submit()->assertUnauthorized();
        $this->getJson('/api/scorm/assignment-submissions?course_id='.$this->course->id)->assertUnauthorized();
        $this->getJson('/api/scorm/assignment-submissions/1')->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_api_failure_has_no_score_or_false_detection_and_same_answer_can_retry(): void
    {
        $error = fn () => Http::response(['error' => ['code' => 'server_error']], 500);
        // The quick automatic retry fails as well, so the submission fails without a score.
        $this->fakeOpenAi(['assignment_detection' => [$error, $error, $this->detection()], 'assignment_grading' => [$this->grade(), $this->grade()]]);
        $this->submit()->assertStatus(503)->assertJsonPath('error', __('assignment.failed'));
        $this->assertUnscoredFailure();
        $this->submit()->assertOk()->assertJsonPath('final_score', 85);
        $this->assertDatabaseCount('assignment_submissions', 1);
    }

    public function test_a_transient_api_error_is_retried_once_within_the_same_submission(): void
    {
        $this->fakeOpenAi([
            'assignment_detection' => [fn () => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429), $this->detection()],
            'assignment_grading' => [fn () => Http::response([], 502), $this->grade()],
        ]);
        $this->submit()->assertOk()->assertJsonPath('final_score', 85);
        Http::assertSentCount(4);
    }

    public static function permanentErrors(): array
    {
        return [[429, 'insufficient_quota'], [400, 'invalid_request_error'], [401, 'invalid_api_key']];
    }

    #[DataProvider('permanentErrors')]
    public function test_quota_and_invalid_request_errors_are_not_retried(int $status, string $code): void
    {
        $this->fakeOpenAi([
            'assignment_detection' => [fn () => Http::response(['error' => ['code' => $code, 'message' => 'x']], $status)],
            'assignment_grading' => [$this->grade()],
        ]);
        $this->submit()->assertStatus(503);
        $this->assertUnscoredFailure();
        Http::assertSentCount(2);
    }

    private function assertUnscoredFailure(): void
    {
        $row = AssignmentSubmission::first();
        $this->assertSame('failed', $row->status);
        $this->assertNull($row->final_score);
        $this->assertNull($row->ai_probability);
        $this->assertNull($row->evaluation_score);
        $this->getJson('/api/scorm/assignment-submissions?course_id='.$this->course->id)->assertJsonCount(0, 'submissions');
    }

    public function test_grading_failure_discards_partial_detection(): void
    {
        $this->fakeOpenAi(['assignment_detection' => [$this->detection()], 'assignment_grading' => [fn () => Http::response([], 500), fn () => Http::response([], 500)]]);
        $this->submit()->assertStatus(503);
        $this->assertUnscoredFailure();
    }

    public function test_network_failure_and_missing_key_do_not_assign_zero(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->submit()->assertStatus(503);
        $this->assertUnscoredFailure();
        config(['services.openai.key' => null]);
        $this->submit()->assertStatus(503);
        $this->assertUnscoredFailure();
    }

    public static function malformedDetection(): array
    {
        return [[['ai_probability' => '65']], [['ai_probability' => 101]], [['ai_probability' => -1]], [['ai_probability' => 50, 'indicators' => []]], [['reason' => []]], [['indicators' => [['category' => 'formulaic_structure', 'quote' => 'not in answer', 'explanation' => 'x']]]]];
    }

    #[DataProvider('malformedDetection')]
    public function test_malformed_detection_is_never_final(array $override): void
    {
        $this->fakeOpenAi(['assignment_detection' => [array_replace($this->detection(), $override)], 'assignment_grading' => [$this->grade()]]);
        $this->submit()->assertStatus(503);
        $this->assertUnscoredFailure();
        Http::assertSentCount(2);
    }

    public function test_refusal_invalid_json_and_incomplete_response_fail_safely(): void
    {
        $this->fakeOpenAi([
            'assignment_detection' => [
                fn () => Http::response(['output' => [['content' => [['type' => 'refusal', 'refusal' => 'no']]]]]),
                fn () => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'invalid']]]]]),
                fn () => Http::response($this->envelope($this->detection(), ['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens']])),
            ],
            'assignment_grading' => array_fill(0, 3, $this->grade()),
        ]);
        for ($i = 0; $i < 3; $i++) {
            $this->submit()->assertStatus(503);
            $this->assertUnscoredFailure();
        }
    }

    public static function malformedGrades(): array
    {
        return [[['material_sufficient' => false]], [['criteria' => []]], [['criteria' => ['accuracy' => ['points' => 500, 'reason' => 'x']]]], [['summary' => []]]];
    }

    #[DataProvider('malformedGrades')]
    public function test_invalid_grade_or_insufficient_material_never_becomes_final(array $override): void
    {
        $this->fake(20, array_replace($this->grade(), $override));
        $this->submit()->assertStatus(503);
        $this->assertUnscoredFailure();
    }

    public function test_zero_rubric_is_normalized_to_minimum_normal_score_of_one(): void
    {
        $grade = $this->grade();
        foreach ($grade['criteria'] as &$criterion) {
            $criterion['points'] = 0;
        }
        $this->fake(20, $grade);
        $this->submit()->assertOk()->assertJsonPath('final_score', 1);
    }

    public function test_each_course_uses_its_own_question_and_txt_in_both_calls(): void
    {
        $other = $this->course(6);
        $this->fake(times: 2);
        $this->submit()->assertOk();
        $this->submit(['course_id' => $other->id])->assertOk()->assertJsonPath('assignment_question', $other->assignmentQuestion());
        $requests = Http::recorded();
        $this->assertCount(4, $requests);
        foreach ($requests as $i => [$request]) {
            $context = $this->sentContext($request);
            $course = $i < 2 ? $this->course : $other;
            $this->assertSame($course->assignmentQuestion(), $context['assignment_question']);
            $this->assertSame($course->title, $context['course_title']);
            $text = json_encode($context['course_material'], JSON_UNESCAPED_UNICODE);
            $this->assertStringContainsString($i < 2 ? 'Ջրի որակը' : 'Համակարգի տարրերը', $text);
            $this->assertStringNotContainsString($i < 2 ? 'Համակարգի տարրերը' : 'Ջրի որակը', $text);
            $this->assertSame(self::ANSWER, $this->sentInput($request)['student_answer']);
            $this->assertTrue($request['text']['format']['strict']);
            $this->assertFalse($request['store']);
            $this->assertSame('medium', $request['reasoning']['effort']);
        }
    }

    public function test_the_whole_course_text_and_question_form_a_cacheable_prefix_shared_by_all_learners(): void
    {
        $this->fake(times: 2);
        $this->submit()->assertOk();
        $this->actingAs(User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password']));
        $this->submit(['student_response' => self::ANSWER.' Լրացուցիչ միտք կրթության մասին։'])->assertOk();

        $requests = collect(Http::recorded())->map(fn ($pair) => $pair[0]);
        foreach ($requests as $request) {
            $this->assertSame(['mode' => 'explicit'], $request['prompt_cache_options']);
            $this->assertSame(['mode' => 'explicit'], $request['input'][0]['content'][1]['prompt_cache_breakpoint']);
            $this->assertSame('developer', $request['input'][0]['role']);
            $this->assertSame('user', $request['input'][1]['role']);
            // The learner's text never enters the cached developer prefix.
            $this->assertStringNotContainsString('Ջրի որակը և կրթության', json_encode($request['input'][0], JSON_UNESCAPED_UNICODE));
        }
        // Same course and prompt: byte-identical prefix for both learners, so the second reads the cache.
        $grading = $requests->filter(fn ($r) => $r['text']['format']['name'] === 'assignment_grading')->values();
        $this->assertSame($grading[0]['input'][0], $grading[1]['input'][0]);
        // Small courses are sent whole rather than as search excerpts.
        $this->assertSame([['heading' => $this->course->title, 'text' => file_get_contents($this->root.'/course8.txt')]], $this->sentContext($grading[0])['course_material']);
    }

    public function test_identical_repeated_submissions_reuse_result_without_api_calls(): void
    {
        $this->fake();
        $first = $this->submit()->assertOk()->json();
        $this->submit()->assertOk()->assertJsonPath('id', $first['id'])->assertJsonPath('final_score', 85);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('assignment_submissions', 1);
    }

    public function test_concurrent_identical_and_different_answers_are_blocked_during_api_call(): void
    {
        $nested = false;
        Http::fake(function ($request) use (&$nested) {
            if (! $nested) {
                $nested = true;
                $this->submit()->assertStatus(409);
                $this->submit(['student_response' => self::ANSWER.' Այլ պատասխան կրթության մասին։'])->assertStatus(409);
            }

            return Http::response($this->envelope($request['text']['format']['name'] === 'assignment_detection' ? $this->detection() : $this->grade()));
        });
        $this->submit()->assertOk();
        Http::assertSentCount(2);
        $this->assertDatabaseCount('assignment_submissions', 1);
    }

    public function test_abandoned_reservation_can_be_reclaimed_and_stale_worker_cannot_finalize(): void
    {
        $this->fakeOpenAi(['assignment_detection' => [fn () => Http::response([], 400), $this->detection()], 'assignment_grading' => [$this->grade(), $this->grade()]]);
        $this->submit()->assertStatus(503);
        AssignmentSubmission::query()->update(['status' => 'processing', 'processing_token' => str_repeat('a', 32), 'processing_expires_at' => now()->subMinute()]);
        $this->submit()->assertOk();
        $this->assertSame(0, AssignmentSubmission::where('processing_token', str_repeat('a', 32))->update(['final_score' => 0]));
    }

    public function test_real_mysql_processes_reserve_only_one_evaluation(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('A real MySQL connection is required for the process race.');
        }
        $connection = config('database.connections.mysql');
        config(['database.connections.assignment_concurrency' => $connection]);
        $db = DB::connection('assignment_concurrency');
        // Independent committed fixtures are visible to both workers; the feature test's
        // ordinary fixtures remain inside RefreshDatabase's transaction.
        $userId = $db->table('users')->insertGetId(['name' => 'Race', 'email' => bin2hex(random_bytes(6)).'@race.example.test', 'password' => 'unused']);
        $courseId = substr(hash('sha256', $this->root), 0, 16);
        $db->table('scorm_courses')->insert(['id' => $courseId, 'number' => 88, 'title' => 'Կայուն զարգացման ինդիկատորներ', 'description' => 'd', 'category' => 'c', 'launch_path' => 'story.html', 'is_available' => true]);
        file_put_contents($this->root.'/course88.txt', 'Կայուն զարգացման ինդիկատորները չափում են մարդկանց կյանքի, տնտեսության և շրջակա միջավայրի փոփոխությունները։');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $connection['host'],
            'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'CACHE_STORE' => 'array', 'ASSIGNMENT_TEST_SOURCE' => $this->root,
            'ASSIGNMENT_TEST_GATE' => $this->root.'/start', 'ASSIGNMENT_TEST_CALLS' => $this->root.'/calls',
            'ASSIGNMENT_TEST_USER_ID' => (string) $userId, 'ASSIGNMENT_TEST_COURSE_ID' => $courseId,
        ];
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/assignment-worker.php')], base_path(), $env);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            touch($this->root.'/start');
            $statuses = [];
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
                $statuses[] = trim($worker->getOutput());
            }
            sort($statuses);
            $this->assertSame(['200', '409'], $statuses);
            $this->assertCount(2, file($this->root.'/calls'));
            $rows = $db->table('assignment_submissions')->where('user_id', $userId)->get();
            $this->assertCount(1, $rows);
            $this->assertSame(85, (int) $rows[0]->final_score);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            $db->table('users')->where('id', $userId)->delete();
            $db->table('scorm_courses')->where('id', $courseId)->delete();
            DB::purge('assignment_concurrency');
        }
    }

    public function test_stale_worker_cannot_overwrite_a_new_reservation(): void
    {
        Http::fake(function ($request) {
            AssignmentSubmission::query()->update(['processing_token' => str_repeat('b', 32)]);

            return Http::response($this->envelope($request['text']['format']['name'] === 'assignment_detection' ? $this->detection() : $this->grade()));
        });
        $this->submit()->assertStatus(409);
        $this->assertDatabaseHas('assignment_submissions', ['status' => 'processing', 'final_score' => null, 'processing_token' => str_repeat('b', 32)]);
    }

    public function test_history_is_paginated_and_excludes_processing_and_failed_submissions(): void
    {
        $this->fake();
        $this->submit()->assertOk();
        $first = AssignmentSubmission::first();
        for ($i = 0; $i < 11; $i++) {
            $first->replicate()->fill(['submission_hash' => hash('sha256', "history-$i")])->save();
        }
        $first->replicate()->fill(['submission_hash' => hash('sha256', 'failed'), 'status' => 'failed', 'final_score' => null])->save();
        $response = $this->getJson('/api/scorm/assignment-submissions?course_id='.$this->course->id)
            ->assertOk()->assertJsonCount(10, 'submissions');
        $this->getJson('/api/scorm/assignment-submissions?course_id='.$this->course->id.'&before='.$response->json('next_before'))
            ->assertOk()->assertJsonCount(2, 'submissions')->assertJsonPath('next_before', null);
        $this->getJson('/api/scorm/assignment-submissions?course_id='.$this->course->id.'&before[]=invalid')->assertOk();
    }

    public function test_changed_question_invalidates_previously_cached_submission(): void
    {
        $this->fake(times: 2);
        $first = $this->submit()->assertOk()->json('id');
        config(['scorm.assignments.questions.8' => 'Բացատրեք կայուն զարգացման ինդիկատորներն ու դրանց չափման եղանակները։']);
        $second = $this->submit()->assertOk()->json('id');
        $this->assertNotSame($first, $second);
        Http::assertSentCount(4);
    }

    public function test_assignment_rate_limit_is_separate_from_question_allowance(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->submit(['student_response' => ''])->assertUnprocessable();
        }
        $this->submit()->assertStatus(429)->assertJsonPath('error', __('assignment.rate_limited'));
        $this->assertSame(0, $this->learner->fresh()->ai_questions_used);
    }

    public function test_course_selection_and_source_availability_are_validated(): void
    {
        $this->submit(['course_id' => []])->assertUnprocessable();
        $this->submit(['course_id' => str_repeat('f', 16)])->assertNotFound();
        $this->course->update(['is_available' => false]);
        $this->submit()->assertNotFound();
        $this->course->update(['is_available' => true]);
        unlink($this->root.'/course8.txt');
        $this->submit()->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_changed_course_text_invalidates_previously_cached_submission(): void
    {
        $this->fake(times: 2);
        $first = $this->submit()->assertOk()->json('id');
        $this->submit()->assertOk()->assertJsonPath('id', $first);
        file_put_contents($this->root.'/course8.txt', file_get_contents($this->root.'/course8.txt').' Կրթության հասանելիությունը նույնպես չափվում է։');
        touch($this->root.'/course8.txt', time() + 5);
        $this->assertNotSame($first, $this->submit()->assertOk()->json('id'));
        Http::assertSentCount(4);
    }

    public function test_course_qa_still_uses_selected_txt_and_records_allowance(): void
    {
        $this->fakeOpenAi(['course_answer' => [['answer' => 'Ջրի որակը չափելի տվյալ է։', 'found' => true]]]);
        $this->postJson('/api/scorm/qa-assistant', ['course_id' => $this->course->id, 'student_question' => 'Ջրի որակը չափելի տվյալ է՞'])
            ->assertOk()->assertJsonPath('answer', 'Ջրի որակը չափելի տվյալ է։');
        $this->assertDatabaseHas('ai_questions', ['course_id' => $this->course->id, 'user_id' => $this->learner->id]);
        $this->assertSame(1, $this->learner->fresh()->ai_questions_used);
    }
}
