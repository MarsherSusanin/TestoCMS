<?php

namespace App\Modules\LLM\Services;

use App\Models\LlmGeneration;
use App\Modules\Core\Contracts\LlmProviderContract;
use Illuminate\Support\Str;
use Throwable;

class LlmGatewayService
{
    /**
     * @param array<string, LlmProviderContract> $providers
     */
    public function __construct(private readonly array $providers)
    {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function generate(string $operation, array $payload, ?int $userId = null): array
    {
        $providerName = (string) ($payload['provider'] ?? config('llm.default_provider', 'openai'));
        $provider = $this->providers[$providerName] ?? null;

        if ($provider === null) {
            return [
                'status' => 'failed',
                'message' => 'Unknown LLM provider: '.$providerName,
            ];
        }

        $sanitizedInput = $this->sanitizePayload($payload);
        $prompt = (string) ($sanitizedInput['prompt'] ?? '');

        if (Str::length($prompt) > (int) config('llm.max_input_chars', 12000)) {
            return [
                'status' => 'failed',
                'message' => 'Prompt is too large.',
            ];
        }

        $record = LlmGeneration::query()->create([
            'operation' => $operation,
            'provider' => $providerName,
            'model' => (string) ($sanitizedInput['model'] ?? ''),
            'status' => 'running',
            'entity_type' => $sanitizedInput['entity_type'] ?? null,
            'entity_id' => $sanitizedInput['entity_id'] ?? null,
            'created_by' => $userId,
            'input_payload' => $sanitizedInput,
        ]);

        try {
            $output = $provider->generate($sanitizedInput);

            $record->update([
                'status' => 'completed',
                'output_payload' => $output,
            ]);

            return [
                'status' => 'ok',
                'generation_id' => $record->id,
                'provider' => $providerName,
                'output' => $output,
                'draft_only' => true,
            ];
        } catch (Throwable $exception) {
            $record->update([
                'status' => 'failed',
                'error_text' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'generation_id' => $record->id,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $patterns = config('llm.mask_secret_patterns', []);

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    $value = (string) preg_replace((string) $pattern, '[REDACTED]', $value);
                }

                $payload[$key] = $value;
            } elseif (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            }
        }

        return $payload;
    }
}
