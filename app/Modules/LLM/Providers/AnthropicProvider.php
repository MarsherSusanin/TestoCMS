<?php

namespace App\Modules\LLM\Providers;

use App\Modules\Core\Contracts\LlmProviderContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicProvider implements LlmProviderContract
{
    public function generate(array $payload): array
    {
        $apiKey = (string) config('llm.providers.anthropic.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $response = Http::baseUrl((string) config('llm.providers.anthropic.base_url'))
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) config('llm.providers.anthropic.version'),
            ])
            ->timeout((int) config('llm.providers.anthropic.timeout', 30))
            ->post('/messages', [
                'model' => $payload['model'] ?? config('llm.providers.anthropic.model'),
                'max_tokens' => 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => (string) ($payload['prompt'] ?? ''),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic provider request failed: '.$response->status());
        }

        return $response->json();
    }
}
