<?php

namespace App\Modules\Content\Services;

use App\Models\CategoryTranslation;
use App\Models\PageTranslation;
use App\Models\PostTranslation;
use App\Modules\Caching\Services\PublicContentVersionService;
use Illuminate\Support\Facades\Cache;

class SlugResolverService
{
    public function __construct(private readonly PublicContentVersionService $versions) {}

    /** @return array{type:string, translation:object, model:object}|null */
    public function resolve(string $locale, string $path): ?array
    {
        $path = trim($path, '/');
        $version = $this->versions->available() ? $this->versions->current() : 'uninstalled';
        $key = $this->key($locale, $path, $version);
        $identity = Cache::get($key);
        if (is_array($identity) && isset($identity['type'], $identity['id'])) {
            $resolved = $this->hydrate($identity['type'], (int) $identity['id'], $locale, $path);
            if ($resolved !== null) {
                return $resolved;
            }
            Cache::forget($key);
        }

        foreach ($this->candidates($path) as [$type, $class, $relation, $slug]) {
            $translation = $class::query()->where('locale', $locale)->where('slug', $slug)->with($relation)->first();
            if ($translation !== null && $translation->{$relation} !== null) {
                // Only identity goes into cache; visibility and content are fetched anew.
                Cache::put($key, ['type' => $type, 'id' => $translation->id], config('cms.slug_cache_ttl', 300));

                return ['type' => $type, 'translation' => $translation, 'model' => $translation->{$relation}];
            }
        }

        return null;
    }

    private function hydrate(string $type, int $id, string $locale, string $path): ?array
    {
        foreach ($this->candidates($path) as [$candidate, $class, $relation, $slug]) {
            if ($candidate !== $type) {
                continue;
            }
            $translation = $class::query()->whereKey($id)->where('locale', $locale)->where('slug', $slug)->with($relation)->first();
            if ($translation !== null && $translation->{$relation} !== null) {
                return ['type' => $type, 'translation' => $translation, 'model' => $translation->{$relation}];
            }
        }

        return null;
    }

    private function candidates(string $path): array
    {
        $candidates = [];
        foreach ([['post', PostTranslation::class, 'post', 'post_url_prefix', 'blog'], ['category', CategoryTranslation::class, 'category', 'category_url_prefix', 'category']] as [$type, $class, $relation, $setting, $default]) {
            $prefix = trim((string) config('cms.'.$setting, $default), '/');
            if ($prefix !== '' && str_starts_with($path, $prefix.'/')) {
                $candidates[] = [$type, $class, $relation, substr($path, strlen($prefix) + 1)];
            }
        }
        $candidates[] = ['page', PageTranslation::class, 'page', $path === '' ? 'home' : $path];

        return $candidates;
    }

    private function key(string $locale, string $path, string $version): string
    {
        return 'cms:slug:'.PublicContentVersionService::CACHE_SCHEMA.':v'.$version.':'.$locale.':'.($path === '' ? 'home' : $path);
    }

    public function flush(string $locale, string $path): void
    {
        $path = trim($path, '/');
        if ($this->versions->available()) {
            Cache::forget($this->key($locale, $path, $this->versions->current()));
        }
        Cache::forget('cms:slug:'.$locale.':'.($path === '' ? 'home' : $path));
    }

    public function flushAllLocales(string $path): void
    {
        foreach ((array) config('cms.supported_locales', ['en']) as $locale) {
            $this->flush((string) $locale, $path);
        }
    }

    public function flushAll(): void
    {
        $this->versions->bump();
        foreach ((array) Cache::get('cms:slug:keys', []) as $key) {
            Cache::forget((string) $key);
        }
        Cache::forget('cms:slug:keys');
    }
}
