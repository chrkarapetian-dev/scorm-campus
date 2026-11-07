<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CourseContentController;
use App\Http\Controllers\CoursePageController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/course/{course}', CoursePageController::class)
        ->where('course', '[a-f0-9]{16}')
        ->name('course.show');

    // Every course file, including its assets, comes through these two routes.
    Route::get('/courses/course{number}/{path}', [CourseContentController::class, 'bundled'])
        ->where(['number' => '[0-9]+', 'path' => '.+'])
        ->name('course.bundled');

    Route::get('/content/{course}/{path}', CourseContentController::class)
        ->where(['course' => '[a-f0-9]{16}', 'path' => '.+'])
        ->name('course.content');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
