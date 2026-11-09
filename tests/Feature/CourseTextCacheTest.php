<?php

namespace Tests\Feature;

use App\Models\ScormCourse;
use App\Services\CourseChunkSearch;
use App\Services\CourseTextChunker;
use App\Services\CourseTextRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CourseTextCacheTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    /** Counts how often a source file is actually read and processed. */
    private object $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/course-texts-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->root);
        config(['scorm.texts_path' => $this->root]);

        $this->chunker = new class extends CourseTextChunker
        {
            public int $reads = 0;

            public function chunk(string $path): array
            {
                $this->reads++;

                return parent::chunk($path);
            }
        };
        $this->app->instance(CourseTextChunker::class, $this->chunker);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function course(int $number, string $text): ScormCourse
    {
        file_put_contents($this->root."/course$number.txt", $text);

        return ScormCourse::create(['id' => substr(md5("cache-$number"), 0, 16), 'number' => $number, 'title' => "Դաս $number", 'description' => 'd', 'category' => 'c', 'launch_path' => 'story.html']);
    }

    /** A fresh repository, as a new request would get. */
    private function repository(): CourseTextRepository
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(CourseTextRepository::class);
    }

    public function test_a_miss_builds_once_and_later_requests_reuse_the_cache(): void
    {
        $course = $this->course(3, "Վերնագիր\nԱռաջին պարբերություն։\n\nԵրկրորդ   պարբերություն։");

        $this->assertSame("Վերնագիր\nԱռաջին պարբերություն։\nԵրկրորդ պարբերություն։", $this->repository()->text($course));
        $this->assertSame(1, $this->chunker->reads);
        $this->assertNotNull(Cache::get('course-text-chunks:3'));

        // New requests, and every lookup within one request, read the cache without touching the file again.
        $repository = $this->repository();
        $repository->text($course);
        $repository->chunks($course);
        $repository->tokens($course);
        $this->repository()->chunks($course);
        $this->assertSame(1, $this->chunker->reads);
    }

    public function test_an_edited_file_rebuilds_its_entry(): void
    {
        $course = $this->course(3, 'Հին տեքստ։');
        $this->repository()->text($course);

        file_put_contents($this->root.'/course3.txt', 'Նոր և ավելի երկար տեքստ։');
        touch($this->root.'/course3.txt', time() + 10);

        $this->assertSame('Նոր և ավելի երկար տեքստ։', $this->repository()->text($course));
        $this->assertSame(2, $this->chunker->reads);
    }

    public function test_courses_have_separate_entries(): void
    {
        $first = $this->course(3, 'Երրորդ դասի տեքստ։');
        $second = $this->course(4, 'Չորրորդ դասի տեքստ։');

        $this->assertSame('Երրորդ դասի տեքստ։', $this->repository()->text($first));
        $this->assertSame('Չորրորդ դասի տեքստ։', $this->repository()->text($second));
        $this->assertSame([['heading' => 'Դաս 4', 'text' => 'Չորրորդ դասի տեքստ։']], app(CourseChunkSearch::class)->relevant($second, 'Ինչ կա'));
    }

    public function test_a_removed_file_or_an_uploaded_course_has_no_text(): void
    {
        $course = $this->course(3, 'Տեքստ։');
        $this->repository()->text($course);
        unlink($this->root.'/course3.txt');

        $this->assertFalse($this->repository()->hasText($course));
        $this->assertSame('', $this->repository()->text($course));
        $this->assertSame([], app(CourseChunkSearch::class)->relevant($course, 'Տեքստ'));

        $uploaded = ScormCourse::create(['id' => str_repeat('c', 16), 'title' => 'Upload', 'description' => 'd', 'category' => 'c', 'launch_path' => 'index.html']);
        $this->assertFalse($this->repository()->hasText($uploaded));
    }

    public function test_token_estimate_errs_on_the_high_side(): void
    {
        // 29,073 characters of course3.txt measured 8,641 gpt-6-luna tokens (3.36 characters each).
        $course = $this->course(3, str_repeat('ա', 3000));

        $this->assertSame(1000, $this->repository()->tokens($course));
    }

    public function test_the_rebuild_command_refreshes_every_course(): void
    {
        $this->course(3, 'Տեքստ։');
        $this->course(4, 'Ուրիշ տեքստ։');

        $this->artisan('courses:rebuild-cache')->assertSuccessful();
        $this->assertSame(2, $this->chunker->reads);
        $this->artisan('courses:warm-cache')->assertSuccessful();
        $this->assertSame(2, $this->chunker->reads);
    }
}
