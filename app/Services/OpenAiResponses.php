<?php

namespace App\Services;

use App\Exceptions\OpenAiRequestFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the OpenAI Responses API. The key stays on the server.
 *
 * Every request is laid out for prompt caching: the instructions and the course material form a
 * developer message that ends in an explicit cache breakpoint, and only the part that changes per
 * request (the question or the learner's answer) follows in a user message. Repeat requests for the
 * same course then read that prefix from the cache at a tenth of the input price.
 */
class OpenAiResponses
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';

    /** Reasoning tokens count toward this limit, so a low cap can leave no visible answer. */
    private const MAX_OUTPUT_TOKENS = 16000;

    /** A failure this quick (seconds) is retried once; a slow one is not, so a request can't take two full timeouts. */
    private const RETRY_WITHIN = 15;

    /** These errors won't go away on retry. */
    private const PERMANENT_CODES = ['insufficient_quota', 'invalid_api_key', 'model_not_found', 'invalid_request_error', 'billing_hard_limit_reached'];

    public function configured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * Runs a prompt that must answer in the given JSON schema.
     *
     * @param  array  $context  stable for this course and task, so it is cached
     * @param  array  $input  what changes with every request
     * @return array{data: array, usage: array{input_tokens: ?int, cached_input_tokens: ?int, output_tokens: ?int}}
     *
     * @throws OpenAiRequestFailed
     */
    public function respond(array $format, string $instructions, array $context, array $input, ?string $effort = null): array
    {
        return $this->respondAll([compact('format', 'instructions', 'context', 'input', 'effort')])[0];
    }

    /**
     * Sends several independent prompts at once and waits for all of them.
     *
     * @param  list<array{format: array, instructions: string, context: array, input: array, effort?: ?string}>  $calls
     * @return list<array{data: array, usage: array}>
     *
     * @throws OpenAiRequestFailed if any of them fails
     */
    public function respondAll(array $calls): array
    {
        $payloads = array_map(fn (array $call) => $this->payload($call), $calls);
        $started = microtime(true);

        $responses = count($payloads) === 1
            ? [$this->send($payloads[0])]
            : Http::pool(function (Pool $pool) use ($payloads) {
                foreach ($payloads as $i => $payload) {
                    $this->request($pool->as("call$i"))->post(self::ENDPOINT, $payload);
                }
            });

        $results = [];

        foreach (array_keys($payloads) as $i) {
            $response = $responses[$i] ?? $responses["call$i"];

            if ($this->retryable($response) && microtime(true) - $started < self::RETRY_WITHIN) {
                usleep(1_000_000);
                $response = $this->send($payloads[$i]);
            }

            $results[] = $this->parse($response);
        }

        return $results;
    }

    private function payload(array $call): array
    {
        return [
            'model' => config('services.openai.model'),
            'reasoning' => ['effort' => $call['effort'] ?? config('services.openai.reasoning_effort')],
            'store' => false,
            'max_output_tokens' => self::MAX_OUTPUT_TOKENS,
            'prompt_cache_options' => ['mode' => 'explicit'],
            'input' => [
                ['role' => 'developer', 'content' => [
                    ['type' => 'input_text', 'text' => $call['instructions']],
                    // Prompts under 1,024 tokens aren't cached; the breakpoint is then simply ignored.
                    ['type' => 'input_text', 'text' => $this->encode($call['context']), 'prompt_cache_breakpoint' => ['mode' => 'explicit']],
                ]],
                ['role' => 'user', 'content' => [
                    ['type' => 'input_text', 'text' => $this->encode($call['input'])],
                ]],
            ],
            'text' => ['format' => $call['format']],
        ];
    }

    private function request(PendingRequest $request): PendingRequest
    {
        return $request->withToken(config('services.openai.key'))
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(config('services.openai.timeout'));
    }

    private function send(array $payload): Response|ConnectionException
    {
        try {
            return $this->request(Http::createPendingRequest())->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            return $e;
        }
    }

    private function retryable(Response|\Throwable $response): bool
    {
        if (! $response instanceof Response) {
            return $response instanceof ConnectionException;
        }

        if (! in_array($response->status(), [408, 409, 429, 500, 502, 503, 504], true)) {
            return false;
        }

        return ! in_array($this->errorCode($response), self::PERMANENT_CODES, true);
    }

    /** @throws OpenAiRequestFailed */
    private function parse(Response|\Throwable $response): array
    {
        if (! $response instanceof Response) {
            throw new OpenAiRequestFailed($response->getMessage(), 'connection_failed');
        }

        if ($response->failed()) {
            throw new OpenAiRequestFailed(
                $response->json('error.message') ?? "API {$response->status()}",
                $this->errorCode($response) ?? "http_{$response->status()}",
            );
        }

        if ($response->json('status') !== null && $response->json('status') !== 'completed') {
            throw new OpenAiRequestFailed('The AI service did not complete its response.', 'incomplete:'.($response->json('incomplete_details.reason') ?? 'unknown'));
        }

        foreach ($response->json('output') ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new OpenAiRequestFailed('The AI service refused to answer.', 'refusal');
                }

                if (($content['type'] ?? null) === 'output_text') {
                    $decoded = json_decode($content['text'] ?? '', true);

                    if (! is_array($decoded)) {
                        throw new OpenAiRequestFailed('The AI service returned invalid JSON.', 'invalid_json');
                    }

                    return ['data' => $decoded, 'usage' => $this->usage($response->json('usage'))];
                }
            }
        }

        throw new OpenAiRequestFailed('The AI service returned no text.', 'no_text');
    }

    private function errorCode(Response $response): ?string
    {
        $code = $response->json('error.code') ?? $response->json('error.type');

        return is_string($code) ? $code : null;
    }

    private function usage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];

        return [
            'input_tokens' => $usage['input_tokens'] ?? null,
            'cached_input_tokens' => $usage['input_tokens_details']['cached_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
        ];
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
