<?php

namespace App\Modules\LLM\Services;

use App\Models\LlmGeneration;
use App\Modules\Core\Contracts\LlmProviderContract;
use App\Modules\LLM\Exceptions\LlmOutputException;
use Illuminate\Support\Str;
use Throwable;

class LlmGatewayService
{
    /**
     * @param  array<string, LlmProviderContract>  $providers
     */
    public function __construct(private readonly array $providers) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generate(string $operation, array $payload, ?int $userId = null): array
    {
        $providerName = (string) ($payload['provider'] ?? config('llm.default_provider', 'openai'));
        $provider = $this->providers[$providerName] ?? null;

        if ($provider === null) {
            return [
                'status' => 'failed',
                'message' => 'Unknown LLM provider.',
                'error_code' => 'llm_unknown_provider',
            ];
        }

        $sanitizedInput = $this->sanitizePayload($payload);
        $prompt = (string) ($sanitizedInput['prompt'] ?? '');

        if (Str::length($prompt) > (int) config('llm.max_input_chars', 12000)) {
            return [
                'status' => 'failed',
                'message' => 'Prompt is too large.',
                'error_code' => 'llm_input_too_large',
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
            $text = app(LlmTextNormalizer::class)->text($providerName, $output);

            $record->update([
                'status' => 'completed',
                'output_payload' => $output,
                'model' => $output['model'] ?? config('llm.providers.'.$providerName.'.model', ''),
            ]);

            return [
                'status' => 'ok',
                'generation_id' => $record->id,
                'provider' => $providerName,
                'output' => $output,
                'text' => $text,
                'draft_only' => true,
            ];
        } catch (Throwable $exception) {
            $record->update([
                'status' => 'failed',
                'error_text' => $exception instanceof LlmOutputException ? $exception->errorCode : 'llm_provider_failed',
                'output_payload' => $output ?? null,
            ]);

            return [
                'status' => 'failed',
                'generation_id' => $record->id,
                'message' => $exception instanceof LlmOutputException ? $exception->getMessage() : 'The LLM provider is unavailable. Try again later.',
                'error_code' => $exception instanceof LlmOutputException ? $exception->errorCode : 'llm_provider_failed',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
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
