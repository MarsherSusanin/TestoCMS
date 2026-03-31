<?php

namespace App\Modules\Content\Services;

use App\Models\CategoryTranslation;
use App\Models\PageTranslation;
use App\Models\PostTranslation;
use Illuminate\Support\Facades\Cache;

class SlugResolverService
{
    private const KEY_LIST = 'cms:slug:keys';

    /**
     * @return array{type: string, translation: object, model: object}|null
     */
    public function resolve(string $locale, string $path): ?array
    {
        $path = trim($path, '/');
        $cacheKey = sprintf('cms:slug:%s:%s', $locale, $path === '' ? 'home' : $path);

        $resolved = Cache::remember($cacheKey, config('cms.slug_cache_ttl', 300), function () use ($locale, $path) {
            $blogPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');
            $categoryPrefix = trim((string) config('cms.category_url_prefix', 'category'), '/');

            if ($blogPrefix !== '' && str_starts_with($path, $blogPrefix.'/')) {
                $slug = substr($path, strlen($blogPrefix) + 1);
                $translation = PostTranslation::query()
                    ->where('locale', $locale)
                    ->where('slug', $slug)
                    ->with('post')
                    ->first();

                if ($translation !== null && $translation->post !== null) {
                    return [
                        'type' => 'post',
                        'translation' => $translation,
                        'model' => $translation->post,
                    ];
                }
            }

            if ($categoryPrefix !== '' && str_starts_with($path, $categoryPrefix.'/')) {
                $slug = substr($path, strlen($categoryPrefix) + 1);
                $translation = CategoryTranslation::query()
                    ->where('locale', $locale)
                    ->where('slug', $slug)
                    ->with('category')
                    ->first();

                if ($translation !== null && $translation->category !== null) {
                    return [
                        'type' => 'category',
                        'translation' => $translation,
                        'model' => $translation->category,
                    ];
                }
            }

            $slug = $path === '' ? 'home' : $path;
            $pageTranslation = PageTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->with('page')
                ->first();

            if ($pageTranslation !== null && $pageTranslation->page !== null) {
                return [
                    'type' => 'page',
                    'translation' => $pageTranslation,
                    'model' => $pageTranslation->page,
                ];
            }

            return null;
        });

        $this->rememberKey($cacheKey);

        return $resolved;
    }

    public function flush(string $locale, string $path): void
    {
        $path = trim($path, '/');
        $cacheKey = sprintf('cms:slug:%s:%s', $locale, $path === '' ? 'home' : $path);
        Cache::forget($cacheKey);
        $this->forgetTrackedKey($cacheKey);
    }

    public function flushAllLocales(string $path): void
    {
        foreach (config('cms.supported_locales', ['en']) as $locale) {
            $this->flush((string) $locale, $path);
        }
    }

    public function flushAll(): void
    {
        $keys = Cache::get(self::KEY_LIST, []);

        foreach ($keys as $key) {
            Cache::forget((string) $key);
        }

        Cache::forget(self::KEY_LIST);
    }

    private function rememberKey(string $cacheKey): void
    {
        $keys = Cache::get(self::KEY_LIST, []);
        if (in_array($cacheKey, $keys, true)) {
            return;
        }

        $keys[] = $cacheKey;
        Cache::forever(self::KEY_LIST, $keys);
    }

    private function forgetTrackedKey(string $cacheKey): void
    {
        $keys = array_values(array_filter(
            Cache::get(self::KEY_LIST, []),
            static fn (mixed $value): bool => (string) $value !== $cacheKey
        ));

        if ($keys === []) {
            Cache::forget(self::KEY_LIST);

            return;
        }

        Cache::forever(self::KEY_LIST, $keys);
    }
}
