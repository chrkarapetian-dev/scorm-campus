<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scorm_courses', function (Blueprint $table) {
            // Set for the bundled courses (storage/courses/course{number}); null for uploaded packages.
            $table->unsignedSmallInteger('number')->nullable()->unique()->after('id');
            $table->boolean('is_available')->default(true)->after('launch_path');
        });

        Schema::create('ai_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('course_id', 16);
            $table->text('question');
            $table->text('answer');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('course_id')->references('id')->on('scorm_courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_questions');

        Schema::table('scorm_courses', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn(['number', 'is_available']);
        });
    }
};
