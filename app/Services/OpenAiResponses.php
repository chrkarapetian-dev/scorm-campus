<?php

namespace App\Services;

use App\Exceptions\OpenAiRequestFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Thin client for the OpenAI Responses API. The key stays on the server. */
class OpenAiResponses
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';

    public function configured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * Runs a prompt that must answer in the given JSON schema.
     *
     * @throws OpenAiRequestFailed
     */
    public function json(array $format, string $instructions, array $input): array
    {
        return $this->respond($format, $instructions, $input)['data'];
    }

    /**
     * Same as json(), and also reports the tokens used.
     *
     * @return array{data: array, usage: ?array}
     *
     * @throws OpenAiRequestFailed
     */
    public function respond(array $format, string $instructions, array $input): array
    {
        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(config('services.openai.timeout'))
                ->post(self::ENDPOINT, [
                    'model' => config('services.openai.model'),
                    'instructions' => $instructions,
                    'input' => json_encode($input, JSON_UNESCAPED_UNICODE),
                    'store' => false,
                    // Reasoning tokens count toward this limit, so a low cap can leave no visible answer.
                    'max_output_tokens' => 16000,
                    'text' => ['format' => $format],
                ]);
        } catch (ConnectionException $e) {
            throw new OpenAiRequestFailed($e->getMessage());
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? [];

            throw new OpenAiRequestFailed(
                $error['message'] ?? "API {$response->status()}",
                $error['code'] ?? $error['type'] ?? null,
            );
        }

        foreach ($response->json('output') ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    $decoded = json_decode($content['text'] ?? '', true);

                    if (is_array($decoded)) {
                        return ['data' => $decoded, 'usage' => $response->json('usage')];
                    }

                    throw new OpenAiRequestFailed('The AI service returned invalid JSON.');
                }
            }
        }

        throw new OpenAiRequestFailed('The AI service returned no text.');
    }
}
