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
            3 => 'Ինչպե՞ս են կայուն զարգացման գաղափարի ձևավորման և տարածման գործում կարևոր դեր խաղացել միջազգային կազմակերպություններն ու համաշխարհային գագաթնաժողովները, և ի՞նչ ազդեցություն են դրանք ունեցել հասարակության ու բնության փոխհարաբերությունների վրա։',
            4 => 'Ինչպե՞ս եք պատկերացնում կայուն զարգացման սկզբունքների կիրառումը մեր առօրյա կյանքում, և ի՞նչ փոփոխություններ կարող են նպաստել ներկա ու ապագա սերունդների կյանքի որակի բարելավմանը։',
            5 => 'Ինչպե՞ս կարելի է համատեղել բնապահպանական, տնտեսական և սոցիալ-մշակութային զարգացման նպատակները՝ ապահովելով արդարություն և հավասար հնարավորություններ թե՛ ներկա, թե՛ ապագա սերունդների համար։',
            6 => 'Ինչպե՞ս է մարդու գործունեությունը փոխում բնական էկոհամակարգերը, և ինչպե՞ս կարող ենք ապահովել բնության ու հասարակության միջև հավասարակշռված փոխհարաբերություններ՝ կայուն զարգացման սկզբունքներին համապատասխան։',
            8 => CourseAssistant::OPEN_QUESTION,
            9 => 'Ինչպե՞ս կարող են ժողովրդագրական, տնտեսական, տեխնոլոգիական, էթիկական և սոցիալական փոփոխությունները նպաստել կայուն զարգացմանը, և ո՞ր ուղղությունն եք համարում առավել կարևոր մերօրյա աշխարհի համար։',
            10 => 'Ինչպե՞ս կարող են պետությունը, միջազգային կազմակերպությունները, մասնավոր հատվածը և քաղաքացիները համագործակցել կայուն զարգացման նպատակներին հասնելու համար, և ո՞րն եք համարում յուրաքանչյուրի ամենակարևոր պատասխանատվությունը։',
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
