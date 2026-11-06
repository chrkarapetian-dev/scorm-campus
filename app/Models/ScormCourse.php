<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A SCORM package uploaded through the app. Its files live on disk under
 * config('scorm.storage_path')/{id}; this row is the catalogue entry.
 */
class ScormCourse extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'title', 'description', 'category', 'launch_path'];

    public function storagePath(): string
    {
        return config('scorm.storage_path').DIRECTORY_SEPARATOR.$this->id;
    }

    /** Public URL of the package's launch page, with each path segment encoded. */
    public function launchUrl(): string
    {
        $path = implode('/', array_map('rawurlencode', explode('/', $this->launch_path)));

        return "/content/{$this->id}/{$path}";
    }
}
