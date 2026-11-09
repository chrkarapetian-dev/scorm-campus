<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Foreign keys also delete these courses' AI questions and assignment submissions.
        DB::table('scorm_courses')->whereIn('number', [1, 2, 7, 11])->delete();

        foreach ([1, 2, 7, 11] as $number) {
            Cache::forget("course-text-chunks:{$number}");
        }
    }

    public function down(): void
    {
        // These courses and their learner records were permanently removed.
    }
};
