<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseProgress extends Model
{
    protected $table = 'course_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['runtime' => 'array', 'activity' => 'array', 'revision' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(ScormCourse::class, 'course_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CourseAnswer::class);
    }
}
