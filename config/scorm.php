<?php

use App\Services\CourseAssistant;

return [
    // Unpacked packages. Mounted as a volume in Docker so courses survive rebuilds.
    'storage_path' => env('SCORM_STORAGE_PATH', storage_path('app/scorm')),

    // Bundled Articulate courses (course{number}) and their source texts (course{number}.txt).
    // Both sit outside public/, so only the authenticated routes can reach them.
    'courses_path' => env('COURSES_PATH', storage_path('courses')),
    'texts_path' => env('COURSE_TEXTS_PATH', storage_path('course_texts')),
    'bundled_course_numbers' => [3, 4, 5, 6, 8, 9, 10],

    // Internal Nginx location for bundled course files. Unset, PHP streams them itself.
    'x_accel_prefix' => env('COURSES_X_ACCEL_PREFIX'),

    // Tokens are estimated from characters. The course texts measured 2.9–3.8 characters per
    // gpt-6-luna token (3.4 on average), so 3.0 errs on the high side.
    'chunks' => [
        'max_tokens' => 700,
        'overlap_tokens' => 80,
        'chars_per_token' => 3.0,
    ],

    // A course whose whole text fits this many (estimated) tokens is sent to the model in full:
    // the largest bundled course is about 8,600 tokens. The text sits in the cached prompt prefix,
    // so repeat requests pay a tenth of the input price for it. Larger texts fall back to the
    // keyword search below.
    'context' => [
        'full_text_max_tokens' => (int) env('AI_FULL_TEXT_MAX_TOKENS', 20000),
    ],

    // Fallback for texts over the budget above.
    'search' => [
        'max_chunks' => (int) env('AI_MAX_CHUNKS', 6),
        // Share of the question's words a chunk must contain to count as relevant.
        'min_coverage' => 0.5,
        // Chunks scoring below this fraction of the best chunk are left out.
        'min_relative_score' => 0.35,
    ],

    'assignments' => [
        'ai_threshold' => max(0, min(100, (int) env('ASSIGNMENT_AI_THRESHOLD', 50))),
        'max_chunks' => 8,
        'questions' => [
            3 => 'Ինչպե՞ս է ձևավորվել կայուն զարգացման հայեցակարգը։ Դասանյութի հիման վրա ներկայացրեք կազմակերպչական գործընթացի հիմնական փուլերը և բացատրեք դրանց կապը։',
            4 => 'Ի՞նչ են նշանակում «կայունություն» և «կայուն զարգացում» հասկացությունները։ Դասանյութի հիման վրա բացատրեք դրանց կապն ու տարբերությունը՝ բերելով հիմնավորված օրինակ։',
            5 => 'Որո՞նք են կայուն զարգացման հայեցակարգի «Ի՞նչ», «Ինչո՞ւ» և «Ինչպե՞ս» հիմնական հարցադրումները։ Բացատրեք դրանց կապը և կիրառեք մեկ օրինակի վրա՝ հիմնվելով դասանյութի վրա։',
            6 => 'Ինչո՞ւ է համակարգային մոտեցումը կայուն զարգացման հայեցակարգի մեթոդաբանական հիմքը։ Բացատրեք համակարգի տարրերի փոխկապակցվածությունը և բերեք դասանյութով հիմնավորված օրինակ։',
            8 => CourseAssistant::OPEN_QUESTION,
            9 => 'Որո՞նք են կայուն զարգացում ապահովելու հիմնական ռազմավարական անցումները։ Դասանյութի հիման վրա բացատրեք դրանց անհրաժեշտությունն ու փոխկապակցվածությունը և բերեք մեկ օրինակ։',
            10 => 'Որո՞նք են կայուն զարգացում ապահովող սուբյեկտները։ Դասանյութի հիման վրա բացատրեք նրանց դերերն ու համագործակցությունը՝ բերելով մեկ հիմնավորված օրինակ։',
        ],
    ],

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
