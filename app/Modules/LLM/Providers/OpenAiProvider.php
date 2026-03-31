<?php

namespace App\Modules\LLM\Providers;

use App\Modules\Core\Contracts\LlmProviderContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements LlmProviderContract
{
    public function generate(array $payload): array
    {
        $apiKey = (string) config('llm.providers.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::baseUrl((string) config('llm.providers.openai.base_url'))
            ->withToken($apiKey)
            ->timeout((int) config('llm.providers.openai.timeout', 30))
            ->post('/responses', [
                'model' => $payload['model'] ?? config('llm.providers.openai.model'),
                'input' => $payload['prompt'] ?? '',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI provider request failed: '.$response->status());
        }

        return $response->json();
    }
}
