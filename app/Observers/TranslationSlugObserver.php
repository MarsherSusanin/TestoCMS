<?php

namespace App\Observers;

use App\Models\PageTranslation;
use App\Models\PostTranslation;
use App\Models\RedirectRule;
use App\Models\SlugHistory;
use App\Modules\Caching\Services\PageCacheService;
use Illuminate\Database\Eloquent\Model;

class TranslationSlugObserver
{
    public function updated(Model $model): void
    {
        if (! $model->isDirty('slug')) {
            return;
        }

        $oldSlug = (string) $model->getOriginal('slug');
        $newSlug = (string) $model->slug;
        $locale = (string) $model->locale;

        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
            return;
        }

        [$entityType, $entityId] = $this->extractEntity($model);

        SlugHistory::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'locale' => $locale,
            'old_slug' => $oldSlug,
            'new_slug' => $newSlug,
        ]);

        $oldPath = $this->buildPath($entityType, $locale, $oldSlug);
        $newPath = $this->buildPath($entityType, $locale, $newSlug);

        // Forward redirect: old URL -> new URL.
        RedirectRule::query()->updateOrCreate(
            ['from_path' => $oldPath],
            [
                'to_path' => $newPath,
                'http_code' => 301,
                'is_active' => true,
            ]
        );

        // Drop the inverse rule (new -> old) so renaming a slug back does not
        // create an infinite redirect loop and leave the page unreachable.
        RedirectRule::query()->where('from_path', $newPath)->delete();

        // Resolve chains: any rule that pointed at the old URL now points
        // straight at the new one (avoids multi-hop 301 chains).
        RedirectRule::query()
            ->where('to_path', $oldPath)
            ->where('from_path', '!=', $newPath)
            ->update(['to_path' => $newPath]);

        app(PageCacheService::class)->flushAll();
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function extractEntity(Model $model): array
    {
        if ($model instanceof PostTranslation) {
            return ['post', (int) $model->post_id];
        }

        if ($model instanceof PageTranslation) {
            return ['page', (int) $model->page_id];
        }

        return ['category', (int) $model->category_id];
    }

    private function buildPath(string $entityType, string $locale, string $slug): string
    {
        $slug = trim($slug, '/');

        if ($entityType === 'post') {
            return '/'.trim($locale.'/'.config('cms.post_url_prefix').'/'.$slug, '/');
        }

        if ($entityType === 'category') {
            return '/'.trim($locale.'/'.config('cms.category_url_prefix').'/'.$slug, '/');
        }

        return '/'.trim($locale.'/'.$slug, '/');
    }
}
