<?php

namespace App\Modules\Content\Services;

use App\Models\ContentTemplate;
use App\Modules\Content\Support\LocalizedContentHelpers;
use Illuminate\Support\Arr;

class PageTemplatePayloadBuilder
{
    use LocalizedContentHelpers;

    public function __construct(
        private readonly PageLayoutNormalizer $pageLayoutNormalizer,
        private readonly SlugUniquenessService $slugUniqueness,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload): array
    {
        $entity = Arr::wrap($payload['entity'] ?? []);
        $translations = Arr::wrap($payload['translations'] ?? []);
        $normalizedTranslations = [];

        foreach ($this->supportedLocales() as $locale) {
            $item = Arr::wrap($translations[$locale] ?? []);
            $blocksJson = $this->normalizeBlocksJson($item['blocks_json'] ?? ($item['content_blocks'] ?? null));

            $normalizedTranslations[$locale] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'slug' => trim((string) ($item['slug'] ?? ''), '/'),
                'blocks_json' => $blocksJson,
                'rich_html' => (string) ($item['rich_html'] ?? ''),
                'meta_title' => $this->normalizeTextarea($item['meta_title'] ?? null),
                'meta_description' => $this->normalizeTextarea($item['meta_description'] ?? null),
                'canonical_url' => $this->normalizeTextarea($item['canonical_url'] ?? null),
                'custom_head_html' => $this->normalizeTextarea($item['custom_head_html'] ?? null),
            ];
        }

        return [
            'entity' => [
                'page_type' => trim((string) ($entity['page_type'] ?? 'landing')) ?: 'landing',
            ],
            'translations' => $normalizedTranslations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPrefill(ContentTemplate $template): array
    {
        $payload = $this->normalizePayload(is_array($template->payload) ? $template->payload : []);
        $entity = Arr::wrap($payload['entity'] ?? []);
        $translations = Arr::wrap($payload['translations'] ?? []);
        $normalizedTranslations = [];

        foreach ($this->supportedLocales() as $locale) {
            $item = Arr::wrap($translations[$locale] ?? []);
            $seedSlug = trim((string) ($item['slug'] ?? ''), '/');
            $slug = $seedSlug !== ''
                ? $this->slugUniqueness->uniquePageSlug($locale, $this->slugUniqueness->duplicateSeed($seedSlug))
                : '';

            $normalizedTranslations[$locale] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'slug' => $slug,
                'blocks_json' => $this->normalizeBlocksJson($item['blocks_json'] ?? null),
                'rich_html' => (string) ($item['rich_html'] ?? ''),
                'meta_title' => $this->normalizeTextarea($item['meta_title'] ?? null),
                'meta_description' => $this->normalizeTextarea($item['meta_description'] ?? null),
                'canonical_url' => $slug !== '' ? $this->defaultCanonicalUrlForPage($locale, $slug) : null,
                'custom_head_html' => $this->normalizeTextarea($item['custom_head_html'] ?? null),
            ];
        }

        return [
            'entity' => [
                'page_type' => trim((string) ($entity['page_type'] ?? 'landing')) ?: 'landing',
            ],
            'translations' => $normalizedTranslations,
        ];
    }

    private function normalizeBlocksJson(mixed $value): string
    {
        if (is_array($value)) {
            return (string) json_encode(
                $this->pageLayoutNormalizer->normalize($value, true),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        if (! is_string($value)) {
            return (string) json_encode($this->pageLayoutNormalizer->emptyLayout(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return (string) json_encode($this->pageLayoutNormalizer->emptyLayout(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            $decoded = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                return (string) json_encode($this->pageLayoutNormalizer->emptyLayout(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return (string) json_encode(
                $this->pageLayoutNormalizer->normalize($decoded, true),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException) {
            return (string) json_encode($this->pageLayoutNormalizer->emptyLayout(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
