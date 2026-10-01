<?php

namespace App\Modules\LLM\Services;

use App\Modules\LLM\Exceptions\LlmOutputException;

/** Extracts provider text without leaking reasoning, tools or JSON into drafts. */
class LlmTextNormalizer
{
    /** @param array<string, mixed> $output */
    public function text(string $provider, array $output): string
    {
        $texts = [];
        if ($provider === 'openai') {
            if (isset($output['error']) || (isset($output['status']) && $output['status'] !== 'completed')) {
                throw new LlmOutputException('llm_incomplete', 'The provider did not complete generation.');
            }
            foreach ($output['output'] ?? [] as $item) {
                if (! is_array($item) || ($item['type'] ?? '') !== 'message') {
                    continue;
                }
                if (($item['status'] ?? 'completed') !== 'completed') {
                    throw new LlmOutputException('llm_incomplete', 'The provider did not complete generation.');
                }
                foreach ($item['content'] ?? [] as $block) {
                    if (! is_array($block)) {
                        continue;
                    }
                    if (($block['type'] ?? '') === 'refusal') {
                        throw new LlmOutputException('llm_refused', 'The provider declined generation.');
                    }
                    if (($block['type'] ?? '') === 'output_text' && is_string($block['text'] ?? null)) {
                        $texts[] = $block['text'];
                    }
                }
            }
        } elseif ($provider === 'anthropic') {
            $reason = $output['stop_reason'] ?? null;
            if ($reason === 'refusal') {
                throw new LlmOutputException('llm_refused', 'The provider declined generation.');
            }
            if (! in_array($reason, ['end_turn', 'stop_sequence'], true)) {
                throw new LlmOutputException('llm_incomplete', 'The provider did not complete generation.');
            }
            foreach ($output['content'] ?? [] as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null)) {
                    $texts[] = $block['text'];
                }
            }
        }
        $text = trim(implode("\n", $texts));
        if ($text === '') {
            throw new LlmOutputException('llm_empty_output', 'The provider returned no usable text.');
        }
        if (mb_strlen($text) > (int) config('llm.max_output_chars', 24000)) {
            throw new LlmOutputException('llm_output_too_large', 'The generated text exceeds the output limit.');
        }

        return $text;
    }
}
