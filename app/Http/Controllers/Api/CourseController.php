<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidScormPackage;
use App\Http\Controllers\Controller;
use App\Models\ScormCourse;
use App\Services\ScormPackageImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ScormCourse::available()->orderBy('number')->latest()->get()->map(fn (ScormCourse $course) => [
            'id' => $course->id,
            'title' => $course->title,
            'desc' => $course->description,
            'category' => $course->category,
            'duration' => 'Ինքնուրույն ուսուցում',
            'done' => 0,
            'color' => 'teal',
            'author' => $course->created_at->format('d.m.Y'),
            'created' => $course->created_at->getTimestampMs(),
            'launch' => $course->launch_path,
        ]));
    }

    public function store(Request $request, ScormPackageImporter $importer): JsonResponse
    {
        if (! $this->mayUpload($request)) {
            return response()->json(['error' => 'Ներբեռնման գաղտնաբառը բացակայում է կամ սխալ է։'], 401);
        }

        $file = $request->file('package');

        if ($file === null || ! $file->isValid()) {
            return response()->json(['error' => 'Ընտրեք SCORM ZIP փաթեթը։'], 400);
        }

        if (! str_ends_with(strtolower($file->getClientOriginalName()), '.zip')) {
            return response()->json(['error' => 'Ընտրեք ZIP ձևաչափով SCORM փաթեթ։'], 400);
        }

        if ($file->getSize() > config('scorm.max_upload_bytes')) {
            return response()->json(['error' => 'Փոխանցվող ֆայլը գերազանցում է թույլատրելի չափը։'], 413);
        }

        try {
            $course = $importer->import($file, $request->input('title'));
        } catch (InvalidScormPackage $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'category' => $course->category,
            'launch' => $course->launch_path,
            'created' => $course->created_at->getTimestampMs(),
            'url' => "/course/{$course->id}",
        ], 201);
    }

    /** Package HTML runs on this origin, so a public deployment must not accept anonymous uploads. */
    private function mayUpload(Request $request): bool
    {
        $token = (string) config('scorm.upload_token');

        if ($token === '') {
            return ! app()->isProduction();
        }

        return hash_equals($token, (string) $request->header('X-Upload-Token'));
    }
}
