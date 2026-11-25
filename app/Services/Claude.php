<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Claude Messages API client on top of Laravel's HTTP client.
 * https://platform.claude.com/docs/en/api/messages
 */
class Claude
{
    public function message(array $payload): array
    {
        $key = config('jobagent.anthropic.key');

        if (blank($key)) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set in .env');
        }

        return Http::baseUrl('https://api.anthropic.com/v1')
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => config('jobagent.anthropic.version'),
            ])
            ->timeout(120)
            // Retry rate limits (429), overloads (529) and server errors.
            ->retry(3, 2000, fn ($e) => $e instanceof \Illuminate\Http\Client\RequestException
                && in_array($e->response->status(), [429, 500, 502, 503, 529], true))
            ->post('/messages', $payload)
            ->throw()
            ->json();
    }

    /** Send a prompt and return the concatenated text of the reply. */
    public function text(string $model, string $system, string $prompt, int $maxTokens = 1024): string
    {
        $response = $this->message([
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return trim(collect($response['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode(''));
    }

    /**
     * Force Claude to answer by calling a single tool, and return that
     * tool's input. This is a reliable way to get structured JSON back.
     */
    public function structured(string $model, string $system, string $prompt, array $tool, int $maxTokens = 1024): array
    {
        $response = $this->message([
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'tools' => [$tool],
            'tool_choice' => ['type' => 'tool', 'name' => $tool['name']],
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $call = collect($response['content'] ?? [])->firstWhere('type', 'tool_use');

        if (! $call) {
            throw new RuntimeException('Claude did not return a tool call.');
        }

        return $call['input'];
    }
}
