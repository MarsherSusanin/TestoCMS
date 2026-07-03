<?php

namespace App\Modules\SEO\Services;

use App\Models\SeoOverride;
use App\Modules\Core\Contracts\SeoResolverContract;
use Illuminate\Support\Facades\Cache;

class SeoResolverService implements SeoResolverContract
{
    private const OVERRIDE_FIELDS = ['meta_title', 'meta_description', 'canonical_url', 'robots_directives', 'structured_data'];

    public const META_TITLE_MAX = 255;

    // Matches the write-path validation ceiling (max:1000) so a saved value is
    // never silently truncated at render — this is a safety cap against
    // multi-kilobyte junk, not an SEO-optimal length.
    public const META_DESCRIPTION_MAX = 1000;

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
        // Clamp by CHARACTER count (mb_substr), matching the max:N validation
        // rules — Str::limit counts display width and would over-truncate CJK.
        $merged['meta_title'] = $this->clampChars($merged['meta_title'], self::META_TITLE_MAX);
        $merged['meta_description'] = $this->clampChars($merged['meta_description'], self::META_DESCRIPTION_MAX);

        return $merged;
    }

    private function clampChars(mixed $value, int $max): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
