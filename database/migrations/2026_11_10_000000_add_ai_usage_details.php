<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_questions', function (Blueprint $table) {
            // Unanswerable questions are logged too (found = false), since they were still paid for.
            $table->boolean('found')->default(true)->after('answer');
            // Input tokens read from OpenAI's prompt cache, billed at a tenth of the input price.
            $table->unsignedInteger('cached_input_tokens')->nullable()->after('input_tokens');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->unsignedInteger('cached_input_tokens')->nullable()->after('input_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_questions', function (Blueprint $table) {
            $table->dropColumn(['found', 'cached_input_tokens']);
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('cached_input_tokens');
        });
    }
};
