<?php

namespace App\Modules\SEO\Services;

use App\Models\SeoOverride;
use App\Modules\Core\Contracts\SeoResolverContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SeoResolverService implements SeoResolverContract
{
    private const OVERRIDE_FIELDS = ['meta_title', 'meta_description', 'canonical_url', 'robots_directives', 'structured_data'];

    public const META_TITLE_MAX = 255;

    public const META_DESCRIPTION_MAX = 500;

    public function resolve(string $entityType, int $entityId, string $locale, array $fallback = []): array
    {
        $defaults = [
            'meta_title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'robots_directives' => config('seo.default_robots', []),
            'structured_data' => null,
        ];

        // Cache `false` (not null) for "no override" so the absence is cached
        // too; SeoOverride model events forget the key on save/delete.
        $override = Cache::remember(
            SeoCacheKeys::override($entityType, $entityId, $locale),
            (int) config('seo.sitemap.cache_ttl', 3600),
            fn () => SeoOverride::query()
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('locale', $locale)
                ->first()?->only(self::OVERRIDE_FIELDS) ?? false,
        );

        $merged = array_merge($defaults, $fallback);

        if (is_array($override)) {
            foreach (self::OVERRIDE_FIELDS as $field) {
                $merged[$field] = $override[$field] ?? $merged[$field];
            }
        }

        if (is_string($merged['canonical_url']) && $merged['canonical_url'] !== '' && ! str_starts_with($merged['canonical_url'], 'http')) {
            $merged['canonical_url'] = rtrim(config('app.url'), '/').'/'.ltrim($merged['canonical_url'], '/');
        }

        // Length guard: nothing upstream should ship a multi-kilobyte string
        // into <title>/<meta name="description">, whatever the data source.
        if (is_string($merged['meta_title'])) {
            $merged['meta_title'] = Str::limit(trim($merged['meta_title']), self::META_TITLE_MAX, '');
        }
        if (is_string($merged['meta_description'])) {
            $merged['meta_description'] = Str::limit(trim($merged['meta_description']), self::META_DESCRIPTION_MAX, '');
        }

        return $merged;
    }
}
