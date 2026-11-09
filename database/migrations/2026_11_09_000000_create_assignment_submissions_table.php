<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('course_id', 16);
            $table->foreign('course_id')->references('id')->on('scorm_courses')->cascadeOnDelete();
            $table->text('assignment_question');
            $table->text('answer');
            // Includes question, source excerpts and policy; identical retries reuse the result.
            $table->char('submission_hash', 64);
            $table->string('status', 20)->default('processing');
            $table->char('processing_token', 32)->nullable();
            $table->timestamp('processing_expires_at')->nullable();
            $table->unsignedTinyInteger('ai_probability')->nullable();
            $table->string('ai_classification', 40)->nullable();
            $table->text('ai_reason')->nullable();
            $table->json('detection_details')->nullable();
            $table->unsignedTinyInteger('ai_threshold');
            $table->unsignedTinyInteger('evaluation_score')->nullable();
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->json('evaluation_feedback')->nullable();
            $table->string('model');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id', 'submission_hash'], 'assignment_submission_unique');
            $table->index(['user_id', 'status', 'processing_expires_at'], 'assignment_processing');
            $table->index(['user_id', 'course_id', 'id'], 'assignment_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
