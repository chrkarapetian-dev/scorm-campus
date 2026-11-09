<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A course in the catalogue: one of the numbered bundled courses, with files in
 * config('scorm.courses_path')/course{number}, or a SCORM package uploaded through
 * the app, with files in config('scorm.storage_path')/{id}.
 */
class ScormCourse extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'number', 'title', 'description', 'category', 'launch_path', 'is_available'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'is_available' => 'boolean'];
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_available', true);
    }

    public function assignmentQuestion(): string
    {
        return config("scorm.assignments.questions.{$this->number}")
            ?? __('assignment.default_question', ['title' => $this->title]);
    }

    public function storagePath(): string
    {
        return $this->number !== null
            ? config('scorm.courses_path').DIRECTORY_SEPARATOR."course{$this->number}"
            : config('scorm.storage_path').DIRECTORY_SEPARATOR.$this->id;
    }

    /** URL of the course's launch page, with each path segment encoded. */
    public function launchUrl(): string
    {
        $path = implode('/', array_map('rawurlencode', explode('/', $this->launch_path)));

        return $this->number !== null ? "/courses/course{$this->number}/{$path}" : "/content/{$this->id}/{$path}";
    }
}
