<?php

namespace App\Modules\SEO\Services;

use App\Models\SeoOverride;
use App\Modules\Core\Contracts\SeoResolverContract;

class SeoResolverService implements SeoResolverContract
{
    public function resolve(string $entityType, int $entityId, string $locale, array $fallback = []): array
    {
        $defaults = [
            'meta_title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'robots_directives' => config('seo.default_robots', []),
            'structured_data' => null,
        ];

        $override = SeoOverride::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('locale', $locale)
            ->first();

        $merged = array_merge($defaults, $fallback);

        if ($override !== null) {
            $merged = array_merge($merged, [
                'meta_title' => $override->meta_title ?? $merged['meta_title'],
                'meta_description' => $override->meta_description ?? $merged['meta_description'],
                'canonical_url' => $override->canonical_url ?? $merged['canonical_url'],
                'robots_directives' => $override->robots_directives ?? $merged['robots_directives'],
                'structured_data' => $override->structured_data ?? $merged['structured_data'],
            ]);
        }

        if (is_string($merged['canonical_url']) && $merged['canonical_url'] !== '' && ! str_starts_with($merged['canonical_url'], 'http')) {
            $merged['canonical_url'] = rtrim(config('app.url'), '/').'/'.ltrim($merged['canonical_url'], '/');
        }

        return $merged;
    }
}
