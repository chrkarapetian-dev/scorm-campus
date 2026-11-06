<?php

return [
    // Unpacked packages. Mounted as a volume in Docker so courses survive rebuilds.
    'storage_path' => env('SCORM_STORAGE_PATH', storage_path('app/scorm')),

    // Secret required by POST /api/courses. Without one, uploads are refused in production.
    'upload_token' => env('UPLOAD_TOKEN'),

    'max_upload_bytes' => 400 * 1024 * 1024,
    'max_expanded_bytes' => 512 * 1024 * 1024,
    'max_entries' => 50_000,

    // Extensions served from a package; anything else is application/octet-stream.
    'mime_types' => [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ],

    // Sections of the bundled Storyline course, matched by its slide ids to track progress.
    'storyline_sections' => [
        ['id' => 'video', 'title' => 'Տեսանյութ', 'slide' => '5WdltCBo0mn', 'video' => true],
        ['id' => 'materials', 'title' => 'Ուսումնական նյութեր', 'slide' => '6m4h0Gy3mUh'],
        ['id' => 'literature', 'title' => 'Գրականություն', 'slide' => '6eZpibFOcqO'],
        ['id' => 'quiz', 'title' => 'Ինքնաստուգիչ հարցեր', 'slide' => '6aymNm6ZUBv', 'resultsSlide' => '6SfrhhbiYCV'],
    ],
];
