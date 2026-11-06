<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $fillable = ['learning_course_id', 'number', 'title', 'summary', 'group_name'];

    protected function casts(): array
    {
        return ['number' => 'integer'];
    }
}
