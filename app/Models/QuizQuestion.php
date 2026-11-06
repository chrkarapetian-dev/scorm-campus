<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $fillable = ['learning_course_id', 'position', 'question', 'options', 'correct_option', 'explanation'];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }
}
