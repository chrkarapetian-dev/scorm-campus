<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Log of answered questions. The allowance itself is users.ai_questions_used. */
class AiQuestion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'course_id', 'question', 'answer', 'input_tokens', 'output_tokens'];
}
