<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LearningCourseController;
use App\Http\Controllers\Api\ProgressController;
use Illuminate\Support\Facades\Route;

// Protected by its upload token instead of a session.
Route::post('/courses', [CourseController::class, 'store']);

// These need the logged-in user, so they run with the session and CSRF protection of the web group.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/learning-course', [LearningCourseController::class, 'show']);
    Route::post('/recommendations', [LearningCourseController::class, 'recommendations']);

    Route::get('/scorm/progress/{course}', [ProgressController::class, 'show']);
    Route::post('/scorm/progress/{course}', [ProgressController::class, 'store']);

    Route::get('/courses', [CourseController::class, 'index']);

    Route::post('/scorm/evaluate-open-ended', [AssignmentController::class, 'store'])->middleware('throttle:assignments');
    Route::get('/scorm/assignment-submissions', [AssignmentController::class, 'index']);
    Route::get('/scorm/assignment-submissions/{submission}', [AssignmentController::class, 'show'])->whereNumber('submission');

    Route::middleware('throttle:assistant')->prefix('scorm')->group(function () {
        Route::post('/qa-assistant', [AssistantController::class, 'question']);

    });
});
