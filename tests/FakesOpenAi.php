<?php

namespace Tests;

use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Fakes the Responses API per prompt (by its JSON schema name), so parallel calls stay deterministic. */
trait FakesOpenAi
{
    /** A completed Responses API reply carrying $data as its JSON output. */
    protected function envelope(array $data, array $extra = []): array
    {
        return $extra + [
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE)]]]],
            'usage' => ['input_tokens' => 100, 'input_tokens_details' => ['cached_tokens' => 60], 'output_tokens' => 50],
        ];
    }

    /**
     * @param  array<string, list<array|Closure|mixed>>  $queues  per schema name: data for a normal reply,
     *                                                            a closure given the request, or an Http::response()
     */
    protected function fakeOpenAi(array $queues): void
    {
        Http::fake(function (Request $request) use (&$queues) {
            $name = $request['text']['format']['name'];

            if (($queues[$name] ?? []) === []) {
                throw new RuntimeException("Unexpected OpenAI call: $name");
            }

            $next = array_shift($queues[$name]);

            return match (true) {
                $next instanceof Closure => $next($request),
                is_array($next) => Http::response($this->envelope($next)),
                default => $next,
            };
        });
    }

    /** The cached developer block of a recorded request: course material and other fixed context. */
    protected function sentContext(Request $request): array
    {
        return json_decode($request['input'][0]['content'][1]['text'], true);
    }

    /** The per-request user message: the question or the learner's answer. */
    protected function sentInput(Request $request): array
    {
        return json_decode($request['input'][1]['content'][0]['text'], true);
    }
}
