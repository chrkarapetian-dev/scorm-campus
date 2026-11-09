<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ai_questions_used' => 'integer',
        ];
    }

    public function aiQuestionsRemaining(): int
    {
        return max(0, config('services.openai.question_limit') - $this->ai_questions_used);
    }

    /** Claims one question. A single conditional UPDATE, so parallel requests can't pass the limit. */
    public function reserveAiQuestion(): bool
    {
        $claimed = static::whereKey($this->id)
            ->where('ai_questions_used', '<', config('services.openai.question_limit'))
            ->increment('ai_questions_used');

        $this->refresh();

        return $claimed === 1;
    }

    public function releaseAiQuestion(): void
    {
        static::whereKey($this->id)->where('ai_questions_used', '>', 0)->decrement('ai_questions_used');

        $this->refresh();
    }

    public function progress(): HasMany
    {
        return $this->hasMany(CourseProgress::class, 'user_id');
    }
}
