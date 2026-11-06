<?php

namespace Tests\Feature;

use App\Models\ScormCourse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class ScormCourseTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/scorm-test-'.bin2hex(random_bytes(4));
        config(['scorm.storage_path' => $this->root, 'scorm.upload_token' => null]);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function manifest(string $href = 'index_lms.html'): string
    {
        return <<<XML
        <manifest><organizations><organization><item identifier="i1" identifierref="res1"><title>T</title></item></organization></organizations>
        <resources><resource identifier="res1" type="webcontent" adlcp:scormtype="sco" href="{$href}"><file href="{$href}"/></resource></resources></manifest>
        XML;
    }

    private function package(array $files, string $name = 'Կայուն-դաս.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'scorm').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $entry => $content) {
            $zip->addFromString($entry, $content);
        }

        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    private function upload(UploadedFile $file, array $data = [], array $headers = [])
    {
        return $this->post('/api/courses', ['package' => $file] + $data, $headers + ['Accept' => 'application/json']);
    }

    public function test_a_valid_package_is_unpacked_and_catalogued(): void
    {
        $response = $this->upload($this->package([
            'imsmanifest.xml' => $this->manifest(),
            'index_lms.html' => '<h1>Դաս</h1>',
            'story_content/video file.mp4' => 'video',
        ]), ['title' => ' Իմ դասը ']);

        $response->assertCreated()->assertJsonFragment(['title' => 'Իմ դասը', 'launch' => 'index_lms.html']);

        $id = $response->json('id');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $id);
        $response->assertJsonFragment(['url' => "/course/{$id}"]);
        $this->assertFileExists("{$this->root}/{$id}/story_content/video file.mp4");
        $this->assertDatabaseHas('scorm_courses', ['id' => $id, 'launch_path' => 'index_lms.html']);
    }

    public function test_title_falls_back_to_the_file_name(): void
    {
        $this->upload($this->package(['imsmanifest.xml' => $this->manifest(), 'index_lms.html' => 'x']))
            ->assertCreated()
            ->assertJsonFragment(['title' => 'Կայուն-դաս']);
    }

    public function test_articulate_launcher_is_replaced_by_the_lms_player(): void
    {
        $this->upload($this->package([
            'pkg/imsmanifest.xml' => $this->manifest('launcher.html'),
            'pkg/launcher.html' => 'popup',
            'pkg/index_lms.html' => 'player',
        ]))->assertCreated()->assertJsonFragment(['launch' => 'pkg/index_lms.html']);
    }

    public function test_a_package_without_a_manifest_is_rejected(): void
    {
        $this->upload($this->package(['index.html' => 'x']))
            ->assertStatus(400)
            ->assertJsonPath('error', 'Չգտնվեց imsmanifest.xml ֆայլը։ Արտահանեք SCORM 1.2 կամ SCORM 2004 փաթեթ Articulate-ից։');
    }

    public function test_a_manifest_pointing_at_a_missing_file_is_rejected(): void
    {
        $this->upload($this->package(['imsmanifest.xml' => $this->manifest('nope.html')]))->assertStatus(400);
        $this->assertSame(0, ScormCourse::count());
    }

    public function test_path_traversal_entries_reject_the_whole_package(): void
    {
        $this->upload($this->package([
            'imsmanifest.xml' => $this->manifest(),
            'index_lms.html' => 'x',
            '../../evil.php' => '<?php',
        ]))->assertStatus(400)->assertJsonPath('error', 'ZIP փաթեթը պարունակում է անվավեր ֆայլի ուղի։');

        $this->assertSame(0, ScormCourse::count());
        $this->assertDirectoryDoesNotExist($this->root);
    }

    public function test_a_package_over_the_expanded_size_limit_is_rejected(): void
    {
        config(['scorm.max_expanded_bytes' => 10]);

        $this->upload($this->package(['imsmanifest.xml' => $this->manifest(), 'index_lms.html' => str_repeat('x', 100)]))
            ->assertStatus(400)
            ->assertJsonPath('error', 'ZIP փաթեթի բացված չափը չափազանց մեծ է։');
    }

    public function test_garbage_and_non_zip_files_are_rejected(): void
    {
        $garbage = tempnam(sys_get_temp_dir(), 'scorm');
        file_put_contents($garbage, 'not a zip');

        $this->upload(new UploadedFile($garbage, 'course.zip', 'application/zip', null, true))->assertStatus(400);
        $this->upload(new UploadedFile($garbage, 'course.txt', 'text/plain', null, true))
            ->assertStatus(400)
            ->assertJsonPath('error', 'Ընտրեք ZIP ձևաչափով SCORM փաթեթ։');
        $this->postJson('/api/courses')->assertStatus(400);
    }

    public function test_the_upload_token_is_enforced_when_configured(): void
    {
        config(['scorm.upload_token' => 'secret']);
        $files = ['imsmanifest.xml' => $this->manifest(), 'index_lms.html' => 'x'];

        $this->upload($this->package($files))->assertUnauthorized();
        $this->upload($this->package($files), [], ['X-Upload-Token' => 'wrong'])->assertUnauthorized();
        $this->upload($this->package($files), [], ['X-Upload-Token' => 'secret'])->assertCreated();
    }

    public function test_production_refuses_uploads_without_a_configured_token(): void
    {
        $this->app['env'] = 'production';

        $this->upload($this->package(['imsmanifest.xml' => $this->manifest(), 'index_lms.html' => 'x']))->assertUnauthorized();
    }

    public function test_content_is_served_with_mime_type_and_security_headers(): void
    {
        $id = $this->upload($this->package([
            'imsmanifest.xml' => $this->manifest(),
            'index_lms.html' => '<h1>Դաս</h1>',
            'story_content/my file.css' => 'body{}',
            'evil.php' => '<?php echo 1;',
        ]))->json('id');

        $page = $this->get("/content/{$id}/index_lms.html");
        $page->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', $page->headers->get('Cache-Control'));

        $this->get("/content/{$id}/story_content/my%20file.css")->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
        $this->get("/content/{$id}/evil.php")->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_range_requests_return_partial_content(): void
    {
        $id = $this->upload($this->package([
            'imsmanifest.xml' => $this->manifest(),
            'index_lms.html' => 'x',
            'video.mp4' => '0123456789',
        ]))->json('id');

        $partial = $this->get("/content/{$id}/video.mp4", ['Range' => 'bytes=2-5']);
        $partial->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10')->assertHeader('Content-Length', '4');

        $this->get("/content/{$id}/video.mp4", ['Range' => 'bytes=-4'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 6-9/10');
        $this->get("/content/{$id}/video.mp4", ['Range' => 'bytes=50-'])->assertOk()->assertHeader('Content-Length', '10');
    }

    public function test_content_outside_a_known_course_is_not_served(): void
    {
        $id = $this->upload($this->package(['imsmanifest.xml' => $this->manifest(), 'index_lms.html' => 'x']))->json('id');
        file_put_contents("{$this->root}/secret.txt", 'secret');

        $this->get("/content/{$id}/missing.html")->assertNotFound();
        $this->get("/content/{$id}/..%2Fsecret.txt")->assertNotFound();
        $this->get('/content/'.str_repeat('a', 16).'/index_lms.html')->assertNotFound();
    }

    public function test_course_page_embeds_the_package(): void
    {
        $id = $this->upload($this->package([
            'imsmanifest.xml' => $this->manifest('story files/index_lms.html'),
            'story files/index_lms.html' => 'x',
        ]), ['title' => '<b>Դաս</b>'])->json('id');

        $this->get("/course/{$id}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee("/content/{$id}/story%20files/index_lms.html", false)
            ->assertSee("usum-scorm-{$id}-", false)
            ->assertDontSee('<b>Դաս</b>', false);

        $this->get('/course/'.str_repeat('a', 16))->assertNotFound()->assertSee('Դասընթացը չի գտնվել։');
    }

    public function test_home_page_renders_the_catalog_with_newest_first(): void
    {
        $this->get('/')->assertOk()->assertSee('Դասընթացներ դեռ չկան։');

        ScormCourse::create(['id' => str_repeat('a', 16), 'title' => 'Հին դաս', 'description' => 'd', 'category' => 'c', 'launch_path' => 'a.html', 'created_at' => now()->subDay()]);
        ScormCourse::create(['id' => str_repeat('b', 16), 'title' => 'Նոր դաս', 'description' => 'd', 'category' => 'c', 'launch_path' => 'b c.html']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Դասընթացներ դեռ չկան։')
            ->assertSeeInOrder(['Նոր դաս', 'Հին դաս', 'Ավելի շատ դասընթացներ'])
            ->assertSee('b%20c.html', false);

        $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonPath('0.title', 'Նոր դաս')
            ->assertJsonPath('0.duration', 'Ինքնուրույն ուսուցում')
            ->assertJsonCount(2);
    }
}
