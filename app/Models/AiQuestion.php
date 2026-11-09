<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log of every question the model answered, with its token usage. Questions the course couldn't
 * answer are kept with found = false; they don't use the allowance, which is users.ai_questions_used.
 */
class AiQuestion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'course_id', 'question', 'answer', 'found', 'input_tokens', 'cached_input_tokens', 'output_tokens'];

    protected function casts(): array
    {
        return ['found' => 'boolean'];
    }
}
