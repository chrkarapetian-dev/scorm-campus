<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LearningCourseController;
use Illuminate\Support\Facades\Route;

// Protected by its upload token instead of a session.
Route::post('/courses', [CourseController::class, 'store']);

// These need the logged-in user, so they run with the session and CSRF protection of the web group.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/learning-course', [LearningCourseController::class, 'show']);
    Route::post('/recommendations', [LearningCourseController::class, 'recommendations']);

    Route::get('/courses', [CourseController::class, 'index']);

    Route::middleware('throttle:assistant')->prefix('scorm')->group(function () {
        Route::post('/qa-assistant', [AssistantController::class, 'question']);
        Route::post('/evaluate-open-ended', [AssistantController::class, 'evaluate']);
    });
});
