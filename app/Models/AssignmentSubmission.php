<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    // Written only from explicit server-generated arrays, never from request input.
    protected $guarded = ['id'];

    protected $hidden = ['processing_token', 'processing_expires_at', 'submission_hash'];

    protected function casts(): array
    {
        return [
            'detection_details' => 'array',
            'evaluation_feedback' => 'array',
            'processing_expires_at' => 'datetime',
            'ai_probability' => 'integer',
            'ai_threshold' => 'integer',
            'evaluation_score' => 'integer',
            'final_score' => 'integer',
        ];
    }
}
