<?php

namespace Tests\Feature;

use App\Models\AiQuestion;
use App\Models\AssignmentSubmission;
use App\Models\ScormCourse;
use App\Models\User;
use Database\Seeders\BundledCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RetiredBundledCoursesTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/retired-courses-'.bin2hex(random_bytes(6));
        config(['scorm.courses_path' => $this->root]);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function course(?int $number): ScormCourse
    {
        return ScormCourse::create([
            'id' => substr(md5($number === null ? 'uploaded' : "course{$number}"), 0, 16),
            'number' => $number,
            'title' => 'Դաս',
            'description' => 'd',
            'category' => 'c',
            'launch_path' => 'story.html',
        ]);
    }

    public function test_migration_removes_retired_courses_and_related_records_only(): void
    {
        $user = User::create(['name' => 'T', 'email' => 'retired@example.com', 'password' => 'password']);
        $this->actingAs($user);
        $kept = $this->course(3);
        $uploaded = $this->course(null);

        foreach ([1, 2, 3, 7, 11] as $number) {
            $course = $number === 3 ? $kept : $this->course($number);
            File::ensureDirectoryExists($this->root."/course{$number}");
            file_put_contents($this->root."/course{$number}/story.html", 'player');
            Cache::forever("course-text-chunks:{$number}", ['text' => 'cached']);
            AiQuestion::create(['user_id' => $user->id, 'course_id' => $course->id, 'question' => 'q', 'answer' => 'a']);
            AssignmentSubmission::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'assignment_question' => 'q',
                'answer' => 'a',
                'submission_hash' => str_repeat('a', 64),
                'ai_threshold' => 50,
                'model' => 'test',
            ]);
        }

        $migration = require database_path('migrations/2026_11_11_000000_remove_retired_bundled_courses.php');
        $migration->up();
        $migration->up();

        $this->assertSame([$uploaded->id, $kept->id], ScormCourse::orderBy('number')->pluck('id')->all());
        $this->assertDatabaseCount('ai_questions', 1);
        $this->assertDatabaseHas('ai_questions', ['course_id' => $kept->id]);
        $this->assertDatabaseCount('assignment_submissions', 1);
        $this->assertDatabaseHas('assignment_submissions', ['course_id' => $kept->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertTrue(Cache::has('course-text-chunks:3'));
        $this->get('/courses/course3/story.html')->assertOk();

        foreach ([1, 2, 7, 11] as $number) {
            $this->assertFalse(Cache::has("course-text-chunks:{$number}"));
            $this->get("/courses/course{$number}/story.html")->assertNotFound();
            $this->get('/course/'.substr(md5("course{$number}"), 0, 16))->assertNotFound();
        }
    }

    public function test_reseeding_preserves_active_ids_without_recreating_retired_courses(): void
    {
        // Even if old packages remain on a host, deploying must not bring them back.
        foreach (range(1, 11) as $number) {
            File::ensureDirectoryExists($this->root."/course{$number}");
        }

        $uploaded = $this->course(null);
        $this->seed(BundledCourseSeeder::class);
        $this->seed(BundledCourseSeeder::class);

        $this->assertSame([3, 4, 5, 6, 8, 9, 10], ScormCourse::whereNotNull('number')->orderBy('number')->pluck('number')->all());
        $this->assertDatabaseCount('scorm_courses', 8);
        $this->assertDatabaseHas('scorm_courses', ['id' => $uploaded->id, 'number' => null]);

        foreach ([3, 4, 5, 6, 8, 9, 10] as $number) {
            $this->assertDatabaseHas('scorm_courses', ['id' => substr(md5("course{$number}"), 0, 16), 'number' => $number, 'is_available' => true]);
        }
    }
}
