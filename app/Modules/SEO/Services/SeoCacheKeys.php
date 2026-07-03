<?php

namespace App\Modules\SEO\Services;

/**
 * Cache keys for the cached SEO endpoints (locale sitemaps, sitemap index,
 * llms.txt). Kept in one place so content-mutation cache flushes and the
 * endpoints themselves can never drift apart on key names.
 */
class SeoCacheKeys
{
    public static function sitemap(string $locale): string
    {
        return 'seo:sitemap:'.strtolower($locale);
    }

    public static function sitemapIndex(): string
    {
        return 'seo:sitemap-index';
    }

    public static function llms(string $locale): string
    {
        return 'seo:llms:'.strtolower($locale);
    }

    /**
     * Per-entity SeoOverride lookup cache; invalidated by SeoOverride model
     * events rather than the content flush in all().
     */
    public static function override(string $entityType, int $entityId, string $locale): string
    {
        return 'seo:override:'.$entityType.':'.$entityId.':'.strtolower($locale);
    }

    /**
     * Every SEO cache key that must be dropped when public content changes.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $keys = [self::sitemapIndex()];

        foreach ((array) config('cms.supported_locales', ['en']) as $locale) {
            $keys[] = self::sitemap((string) $locale);
            $keys[] = self::llms((string) $locale);
        }

        return $keys;
    }
}
