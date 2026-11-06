<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'learning_course_id', 'position', 'kind', 'title', 'label', 'lead', 'duration', 'html'];

    /** The lesson body as plain text, for use as model context. */
    public function plainText(): string
    {
        $text = preg_replace('/<[^>]*>/u', ' ', $this->html);
        $text = str_replace(['&nbsp;', '&amp;'], [' ', '&'], $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
