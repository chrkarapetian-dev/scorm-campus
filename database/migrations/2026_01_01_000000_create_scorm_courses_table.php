<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorm_courses', function (Blueprint $table) {
            $table->char('id', 16)->primary();
            $table->string('title', 255);
            $table->string('description');
            $table->string('category');
            $table->string('launch_path', 800);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorm_courses');
    }
};
