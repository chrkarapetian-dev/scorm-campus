<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LearningCourseController;
use Illuminate\Support\Facades\Route;

Route::get('/learning-course', [LearningCourseController::class, 'show']);
Route::post('/recommendations', [LearningCourseController::class, 'recommendations']);

Route::get('/courses', [CourseController::class, 'index']);
Route::post('/courses', [CourseController::class, 'store']);

Route::middleware('throttle:assistant')->prefix('scorm')->group(function () {
    // Needs the logged-in user, so it runs with the session and CSRF protection of the web group.
    Route::post('/qa-assistant', [AssistantController::class, 'question'])->middleware(['web', 'auth']);
    Route::post('/evaluate-open-ended', [AssistantController::class, 'evaluate']);
});
