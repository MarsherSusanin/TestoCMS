<?php

namespace App\Modules\Content\Services;

use App\Models\User;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Core\Contracts\ContentRevisionServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class ContentMutationFinalizerService
{
    public function __construct(
        private readonly ContentRevisionServiceContract $revisionService,
        private readonly AuditLogger $auditLogger,
        private readonly PageCacheService $pageCacheService,
        private readonly SlugResolverService $slugResolver,
    ) {}

    /**
     * @param  array<int, string>  $relations
     */
    public function finalize(Model $entity, string $entityType, array $relations, User $actor, array $context): Model
    {
        $entity->refresh()->load($relations);
        $this->flushSlugCache($entityType, $entity);

        if (($context['snapshot'] ?? true) === true) {
            $this->revisionService->snapshot($entityType, (int) $entity->getKey(), $entity->toArray(), $actor->id);
        }

        $auditAction = $context['audit_action'] ?? null;
        if (is_string($auditAction) && $auditAction !== '') {
            $this->auditLogger->log(
                $auditAction,
                $entity,
                is_array($context['audit_context'] ?? null) ? $context['audit_context'] : [],
                request(),
                $actor->id
            );
        }

        if (($context['flush_cache'] ?? true) === true) {
            $this->pageCacheService->flushAll();
        }

        return $entity;
    }

    private function flushSlugCache(string $entityType, Model $entity): void
    {
        if (! in_array($entityType, ['page', 'post'], true) || ! $entity->relationLoaded('translations')) {
            return;
        }

        foreach ($entity->translations as $translation) {
            $locale = strtolower((string) ($translation->locale ?? ''));
            $slug = (string) ($translation->slug ?? '');
            if ($locale === '' || $slug === '') {
                continue;
            }

            $path = $entityType === 'post'
                ? trim((string) config('cms.post_url_prefix', 'blog'), '/').'/'.$slug
                : $slug;

            $this->slugResolver->flush($locale, $path);
        }
    }
}
