<?php

// A separate PHP process for the MySQL reservation race test. Never calls the real API.
use App\Http\Controllers\Api\AssignmentController;
use App\Models\User;
use App\Services\AssignmentEvaluator;
use App\Services\CourseChunkSearch;
use App\Services\CourseTextRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['cache.default' => 'array', 'services.openai.key' => 'test-only', 'scorm.texts_path' => getenv('ASSIGNMENT_TEST_SOURCE')]);
$deadline = microtime(true) + 10;
while (! is_file(getenv('ASSIGNMENT_TEST_GATE'))) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(1000);
}
Http::preventStrayRequests();
Http::fake(function ($request) {
    file_put_contents(getenv('ASSIGNMENT_TEST_CALLS'), "call\n", FILE_APPEND | LOCK_EX);
    usleep(500000);
    $data = $request['text']['format']['name'] === 'assignment_detection'
        ? ['ai_probability' => 20, 'reason' => 'Մոտավոր գնահատում։', 'indicators' => [], 'counter_indicators' => ['Հեղինակությունը որոշել հնարավոր չէ։']]
        : ['material_sufficient' => true, 'criteria' => ['accuracy' => ['points' => 30, 'reason' => 'Ճիշտ է։'], 'understanding' => ['points' => 25, 'reason' => 'Կապերը կան։'], 'completeness' => ['points' => 20, 'reason' => 'Թերի է։'], 'clarity' => ['points' => 10, 'reason' => 'Հստակ է։']], 'summary' => 'Լավ է։', 'correct_points' => [], 'missing_points' => [], 'improvements' => []];

    return Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]]);
});
$request = Request::create('/api/scorm/evaluate-open-ended', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
    'course_id' => getenv('ASSIGNMENT_TEST_COURSE_ID'), 'student_response' => str_repeat('Կայուն զարգացման ինդիկատորներ։ ', 10),
]));
// Deliberately resolve via a DB read each time: reservation must use current locking reads even
// when a transaction has already established a repeatable-read snapshot.
$request->setUserResolver(fn () => User::findOrFail(getenv('ASSIGNMENT_TEST_USER_ID')));
$response = app(AssignmentController::class)->store($request, app(CourseTextRepository::class), app(CourseChunkSearch::class), app(AssignmentEvaluator::class));
echo $response->getStatusCode();
