<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The built-in course whose material grounds the assistant and the grader. */
class LearningCourse extends Model
{
    public const MAIN = 'topic-8-sustainable-development';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'title', 'short_title', 'language'];

    public static function main(): self
    {
        return static::with(['lessons', 'goals'])->findOrFail(self::MAIN);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class)->orderBy('number');
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }
}
