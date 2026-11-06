<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CourseAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    private const MAX_BODY_BYTES = 100_000;

    public function __construct(private readonly CourseAssistant $assistant) {}

    public function question(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);

        if ($body === null) {
            return response()->json(['error' => 'Հարցման տվյալները սխալ են։'], 400);
        }

        $question = mb_substr($this->text($body, 'student_question'), 0, 1200);

        if ($question === '') {
            return response()->json(['error' => 'Գրեք ձեր հարցը։'], 400);
        }

        $user = $request->user();

        if (! $user->reserveAiQuestion()) {
            return response()->json(['error' => 'Դուք օգտագործել եք AI-ին հարց տալու ձեր բոլոր հնարավորությունները։', 'questions_remaining' => 0], 403);
        }

        $result = $this->assistant->answer($question, $this->text($body, 'active_section'));

        // An OpenAI failure isn't the learner's fault, so it doesn't use up a question.
        if ($result['ai_failed'] ?? false) {
            $user->releaseAiQuestion();
        }

        unset($result['ai_failed']);

        return response()->json($result + ['questions_remaining' => $user->aiQuestionsRemaining()]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);

        if ($body === null) {
            return response()->json(['error' => 'Հարցման տվյալները սխալ են։'], 400);
        }

        $answer = mb_substr($this->text($body, 'student_response'), 0, 6000);

        if (mb_strlen($answer) < 40) {
            return response()->json(['error' => 'Գրեք առնվազն 40 նիշ՝ հարցին ամբողջական պատասխանելու համար։'], 400);
        }

        return response()->json($this->assistant->evaluate($answer));
    }

    private function jsonBody(Request $request): ?array
    {
        $content = $request->getContent();

        if (strlen($content) > self::MAX_BODY_BYTES) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function text(array $body, string $key): string
    {
        $value = $body[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
