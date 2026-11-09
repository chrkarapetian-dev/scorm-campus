<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('course_id', 16);
            $table->foreign('course_id')->references('id')->on('scorm_courses')->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(0);
            $table->json('runtime');
            $table->json('activity');
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });
        Schema::create('course_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_progress_id')->constrained('course_progress')->cascadeOnDelete();
            $table->string('question_id', 191);
            $table->json('data');
            $table->timestamps();
            $table->unique(['course_progress_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_answers');
        Schema::dropIfExists('course_progress');
    }
};
