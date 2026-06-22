<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\RedirectRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Cleans up rows that reference a Page/Post/Category by (entity_type, entity_id)
 * but have no foreign key, plus the auto-generated redirect rules that point at
 * the entity's URLs. Without this, deleting an entity orphaned its SEO
 * overrides, revisions, slug history, preview tokens and publish schedules, and
 * left stale 301s that 404'd or hijacked a later-reused slug.
 */
class ContentEntityCleanupObserver
{
    /** @var list<string> */
    private const ENTITY_KEYED_TABLES = [
        'seo_overrides',
        'content_revisions',
        'preview_tokens',
        'publish_schedules',
        'slug_histories',
    ];

    public function deleting(Model $model): void
    {
        [$entityType, $entityId] = $this->resolveEntity($model);
        if ($entityType === '' || $entityId <= 0) {
            return;
        }

        $model->loadMissing('translations');
        $paths = [];
        foreach ($model->translations as $translation) {
            $locale = trim((string) ($translation->locale ?? ''));
            $slug = trim((string) ($translation->slug ?? ''));
            if ($locale !== '' && $slug !== '') {
                $paths[] = $this->buildPath($entityType, $locale, $slug);
            }
        }

        DB::transaction(function () use ($entityType, $entityId, $paths): void {
            foreach (self::ENTITY_KEYED_TABLES as $table) {
                DB::table($table)
                    ->where('entity_type', $entityType)
                    ->where('entity_id', $entityId)
                    ->delete();
            }

            if ($paths !== []) {
                RedirectRule::query()
                    ->where(function ($query) use ($paths): void {
                        $query->whereIn('from_path', $paths)->orWhereIn('to_path', $paths);
                    })
                    ->delete();
            }
        });
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveEntity(Model $model): array
    {
        return match (true) {
            $model instanceof Post => ['post', (int) $model->id],
            $model instanceof Page => ['page', (int) $model->id],
            $model instanceof Category => ['category', (int) $model->id],
            default => ['', 0],
        };
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
