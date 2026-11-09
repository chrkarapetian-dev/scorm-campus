<?php

namespace Tests\Feature;

use App\Models\CourseProgress;
use App\Models\ScormCourse;
use App\Models\User;
use App\Services\Progress\StorylineQuestionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProgressPresentationTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private string $url;

    private ScormCourse $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/progress-presentation-'.bin2hex(random_bytes(5));
        config(['app.debug' => false, 'cache.default' => 'array', 'scorm.courses_path' => $this->root]);
        $this->writePackage(json_decode(file_get_contents(base_path('tests/Fixtures/storyline-questions.json')), true));
        $this->course = ScormCourse::create(['id' => str_repeat('c', 16), 'number' => 3, 'title' => 'Դաս 3', 'description' => 'd', 'category' => 'c', 'launch_path' => 'index_lms.html']);
        $this->url = '/api/scorm/progress/'.$this->course->id;
        $this->actingAs(User::create(['name' => 'T', 'email' => 'presentation@example.test', 'password' => 'password']));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function writePackage(array $data): void
    {
        $directory = $this->root.'/course3/html5/data/js';
        File::ensureDirectoryExists($directory);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $literal = str_replace(['\\', "'"], ['\\\\', "\\'"], $json);
        file_put_contents($directory.'/data.js', "\xEF\xBB\xBFwindow.globalProvideData('data', '{$literal}');");
    }

    public function test_existing_encoded_records_are_readable_in_natural_question_order_without_rewriting_them(): void
    {
        $raw = '____________________________(______________________________________)__________________________________________25-40%-__________________________________________:';
        $record = CourseProgress::create(['user_id' => auth()->id(), 'course_id' => $this->course->id, 'revision' => 0, 'runtime' => ['cmi.suspend_data' => 'unchanged'], 'activity' => ['materials' => ['visited' => true, 'completed' => true]]]);
        $record->answers()->create(['question_id' => 'Scene5_Slide10_MultiChoice_0_0', 'data' => ['selected' => $raw, 'correct' => [$raw], 'result' => 'correct', 'description' => null]]);
        $response = $this->getJson($this->url)->assertOk();
        $response->assertJsonPath('answers.0.questionId', 'Scene5_Slide2_MultiChoice_0_0');
        $answer = $response->json('answers.2');
        $this->assertSame('Scene5_Slide10_MultiChoice_0_0', $answer['questionId']);
        $this->assertStringContainsString('25-40%', $answer['selected']);
        $this->assertMatchesRegularExpression('/[Ա-Ֆա-ֆ]/u', $answer['description']);
        $this->assertStringNotContainsString('___', $answer['selected']);
        $this->assertSame($answer['selected'], $answer['correct'][0]);
        $this->assertSame($raw, $record->answers()->first()->data['selected']);
        $this->assertSame('unchanged', $record->fresh()->runtime['cmi.suspend_data']);
        $response->assertJsonPath('completedSections', 1)->assertJsonPath('quizProgress.answered', 1)->assertJsonPath('quizProgress.total', 4)->assertJsonPath('quiz', null);
    }

    public function test_unanswered_questions_are_explicit_and_do_not_reveal_correct_choices(): void
    {
        $this->getJson($this->url)->assertOk()->assertJsonCount(4, 'answers')->assertJsonPath('answers.0.answered', false)->assertJsonPath('answers.0.selected', null)->assertJsonPath('answers.0.correct', [])->assertJsonPath('quizProgress.answered', 0);
    }

    public function test_lossy_choice_collisions_are_reported_without_guessing(): void
    {
        $catalog = app(StorylineQuestionCatalog::class);
        $question = $catalog->forCourse($this->course)['Scene5_Slide6_MultiChoice_0_0'];
        $duplicates = array_filter(array_count_values($question['codes']), fn ($count) => $count > 1);
        $this->assertNotEmpty($duplicates);
        $display = $catalog->display($question, ['selected' => array_key_first($duplicates), 'result' => 'wrong']);
        $this->assertTrue($display['answered']);
        $this->assertTrue($display['selectionUnavailable']);
        $this->assertNull($display['selected']);
        $this->assertSame('incorrect', $display['result']);
    }

    public function test_reported_scores_remain_provisional_until_the_package_submits_its_attempt(): void
    {
        $definitions = app(StorylineQuestionCatalog::class)->forCourse($this->course);
        $runtime = ['cmi.core.lesson_status' => 'incomplete', 'cmi.core.score.raw' => '50'];
        foreach (array_values($definitions) as $i => $question) {
            $runtime["cmi.interactions.{$i}.id"] = $question['questionId'];
            $runtime["cmi.interactions.{$i}.student_response"] = array_key_first($question['choices']);
            $runtime["cmi.interactions.{$i}.result"] = $i < 2 ? 'correct' : 'wrong';
        }
        $this->postJson($this->url, ['revision' => 0, 'runtime' => $runtime])->assertOk()->assertJsonPath('quiz', null)->assertJsonPath('quizScore.score', 50)->assertJsonPath('quizScore.final', false)->assertJsonPath('sections.quiz.completed', false)->assertJsonPath('quizProgress.answered', 4);
        $this->postJson($this->url, ['revision' => 1, 'reset' => true])->assertOk()->assertJsonPath('quiz', null)->assertJsonPath('quizProgress.answered', 0);
    }

    public function test_missing_or_changed_packages_and_unknown_questions_have_safe_fallbacks(): void
    {
        $record = CourseProgress::create(['user_id' => auth()->id(), 'course_id' => $this->course->id, 'revision' => 0, 'runtime' => [], 'activity' => []]);
        $record->answers()->create(['question_id' => 'Scene5_Slide99_MultiChoice_0_0', 'data' => ['selected' => '________', 'result' => 'wrong']]);
        File::deleteDirectory($this->root.'/course3');
        $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'answers')->assertJsonPath('answers.0.description', null)->assertJsonPath('answers.0.selected', null)->assertJsonPath('answers.0.selectionUnavailable', true);
    }

    public function test_available_bundled_packages_have_readable_questions_in_quiz_order(): void
    {
        if (! is_file(storage_path('courses/course3/html5/data/js/data.js'))) {
            $this->markTestSkipped('Bundled package files are not available in this checkout.');
        }
        config(['scorm.courses_path' => storage_path('courses')]);
        foreach (config('scorm.bundled_course_numbers') as $number) {
            $course = new ScormCourse(['number' => $number]);
            $questions = app(StorylineQuestionCatalog::class)->forCourse($course);
            $this->assertCount(10, $questions);
            $this->assertSame('Scene5_Slide2_MultiChoice_0_0', array_key_first($questions));
            $this->assertSame('Scene5_Slide11_MultiChoice_0_0', array_key_last($questions));
            foreach ($questions as $question) {
                $this->assertMatchesRegularExpression('/[Ա-Ֆա-ֆ]/u', $question['description']);
                $this->assertGreaterThanOrEqual(2, count($question['choices']));
                if ($number === 8 && $question['questionId'] === 'Scene5_Slide9_MultiChoice_0_0') {
                    // This published question marks every option incorrect. Never invent a correct choice.
                    $this->assertSame([], $question['correct']);
                } else {
                    $this->assertCount(1, $question['correct']);
                }
            }
        }
    }

    public function test_malformed_metadata_does_not_break_the_progress_endpoint(): void
    {
        $this->writePackage(['scenes' => ['invalid'], 'quizzes' => [['sliderefs' => [['id' => []]]]]]);
        $this->getJson($this->url)->assertOk()->assertJsonPath('answers', []);
    }

    public function test_package_wrapper_is_parsed_without_running_code_and_cache_rebuilds_when_it_changes(): void
    {
        $catalog = app(StorylineQuestionCatalog::class);
        $this->assertCount(4, $catalog->forCourse($this->course));
        $path = $this->root.'/course3/html5/data/js/data.js';
        file_put_contents($path, "window.globalProvideData('data', '{}'); maliciousCode();");
        $this->assertSame([], $catalog->forCourse($this->course));
        $this->writePackage(['scenes' => [], 'quizzes' => []]);
        $this->assertSame([], $catalog->forCourse($this->course));
    }
}
