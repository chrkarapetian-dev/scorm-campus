<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_courses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('short_title');
            $table->string('language');
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('learning_course_id');
            $table->unsignedTinyInteger('position');
            $table->string('kind');
            $table->string('title');
            $table->string('label');
            $table->text('lead');
            $table->string('duration');
            $table->text('html');
            $table->timestamps();

            $table->foreign('learning_course_id')->references('id')->on('learning_courses')->cascadeOnDelete();
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('learning_course_id');
            $table->unsignedTinyInteger('number');
            $table->string('title');
            $table->text('summary');
            $table->string('group_name');
            $table->timestamps();

            $table->foreign('learning_course_id')->references('id')->on('learning_courses')->cascadeOnDelete();
            $table->unique(['learning_course_id', 'number']);
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->string('learning_course_id');
            $table->unsignedTinyInteger('position');
            $table->text('question');
            $table->json('options');
            $table->unsignedTinyInteger('correct_option');
            $table->text('explanation');
            $table->timestamps();

            $table->foreign('learning_course_id')->references('id')->on('learning_courses')->cascadeOnDelete();
            $table->unique(['learning_course_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('learning_courses');
    }
};
