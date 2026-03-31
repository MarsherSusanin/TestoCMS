<?php

namespace App\Modules\Content\Services;

use App\Models\ContentTemplate;
use App\Modules\Content\Support\LocalizedContentHelpers;
use Illuminate\Support\Arr;

class PostTemplatePayloadBuilder
{
    use LocalizedContentHelpers;

    public function __construct(private readonly SlugUniquenessService $slugUniqueness) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload): array
    {
        $entity = Arr::wrap($payload['entity'] ?? []);
        $translations = Arr::wrap($payload['translations'] ?? []);
        $categoryIds = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            is_array($entity['category_ids'] ?? null) ? $entity['category_ids'] : []
        ), static fn (int $id): bool => $id > 0));
        $featuredAssetId = (int) ($entity['featured_asset_id'] ?? 0);
        $normalizedTranslations = [];

        foreach ($this->supportedLocales() as $locale) {
            $item = Arr::wrap($translations[$locale] ?? []);
            $contentFormat = strtolower(trim((string) ($item['content_format'] ?? 'html')));
            if (! in_array($contentFormat, ['html', 'markdown'], true)) {
                $contentFormat = 'html';
            }

            $normalizedTranslations[$locale] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'slug' => trim((string) ($item['slug'] ?? ''), '/'),
                'content_format' => $contentFormat,
                'content_html' => (string) ($item['content_html'] ?? ''),
                'content_markdown' => (string) ($item['content_markdown'] ?? ''),
                'excerpt' => $this->normalizeTextarea($item['excerpt'] ?? null),
                'meta_title' => $this->normalizeTextarea($item['meta_title'] ?? null),
                'meta_description' => $this->normalizeTextarea($item['meta_description'] ?? null),
                'canonical_url' => $this->normalizeTextarea($item['canonical_url'] ?? null),
                'custom_head_html' => $this->normalizeTextarea($item['custom_head_html'] ?? null),
            ];
        }

        return [
            'entity' => [
                'featured_asset_id' => $featuredAssetId > 0 ? $featuredAssetId : null,
                'category_ids' => $categoryIds,
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
        $categoryIds = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            is_array($entity['category_ids'] ?? null) ? $entity['category_ids'] : []
        ), static fn (int $id): bool => $id > 0));
        $featuredAssetId = (int) ($entity['featured_asset_id'] ?? 0);
        $normalizedTranslations = [];

        foreach ($this->supportedLocales() as $locale) {
            $item = Arr::wrap($translations[$locale] ?? []);
            $seedSlug = trim((string) ($item['slug'] ?? ''), '/');
            $slug = $seedSlug !== ''
                ? $this->slugUniqueness->uniquePostSlug($locale, $this->slugUniqueness->duplicateSeed($seedSlug))
                : '';
            $contentFormat = strtolower(trim((string) ($item['content_format'] ?? 'html')));
            if (! in_array($contentFormat, ['html', 'markdown'], true)) {
                $contentFormat = 'html';
            }

            $normalizedTranslations[$locale] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'slug' => $slug,
                'content_format' => $contentFormat,
                'content_html' => (string) ($item['content_html'] ?? ''),
                'content_markdown' => (string) ($item['content_markdown'] ?? ''),
                'excerpt' => $this->normalizeTextarea($item['excerpt'] ?? null),
                'meta_title' => $this->normalizeTextarea($item['meta_title'] ?? null),
                'meta_description' => $this->normalizeTextarea($item['meta_description'] ?? null),
                'canonical_url' => $slug !== '' ? $this->defaultCanonicalUrlForPost($locale, $slug) : null,
                'custom_head_html' => $this->normalizeTextarea($item['custom_head_html'] ?? null),
            ];
        }

        return [
            'entity' => [
                'featured_asset_id' => $featuredAssetId > 0 ? $featuredAssetId : null,
                'category_ids' => $categoryIds,
            ],
            'translations' => $normalizedTranslations,
        ];
    }
}
