<?php

namespace App\Modules\Content\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Contracts\PageContentServiceContract;
use App\Modules\Content\Contracts\PageWorkflowServiceContract;
use App\Modules\Content\Contracts\PostContentServiceContract;
use App\Modules\Content\Contracts\PostWorkflowServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ContentBulkActionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PageCacheService $pageCacheService,
        private readonly PageContentServiceContract $pageContent,
        private readonly PostContentServiceContract $postContent,
        private readonly PageWorkflowServiceContract $pageWorkflow,
        private readonly PostWorkflowServiceContract $postWorkflow,
    ) {}

    /**
     * @param  array<int, int>  $ids
     * @return array<string, mixed>
     */
    public function applyPages(User $actor, string $action, array $ids): array
    {
        $items = Page::query()
            ->with('translations')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        $request = request();

        return $this->applyBulk(
            actor: $actor,
            ids: $ids,
            action: $action,
            resolve: static fn (int $id) => $items->get($id),
            execute: function (Page $page, string $resolvedAction) use ($actor, $request): void {
                if ($resolvedAction === 'unpublish') {
                    $this->pageWorkflow->unpublish($page, $request, ['audit_action' => 'page.unpublish.web']);

                    return;
                }

                if ($resolvedAction === 'duplicate') {
                    $this->pageContent->duplicate($page, $actor, [
                        'audit_action' => null,
                        'snapshot' => false,
                        'flush_cache' => false,
                    ]);

                    return;
                }

                if ($resolvedAction === 'delete') {
                    $this->pageWorkflow->destroy($page, $request, ['audit_action' => 'page.delete.web']);
                }
            },
            allowed: static function (User $user, Page $page, string $resolvedAction): bool {
                if ($resolvedAction === 'unpublish') {
                    return Gate::forUser($user)->allows('publish', $page);
                }

                if ($resolvedAction === 'duplicate') {
                    return Gate::forUser($user)->allows('create', Page::class);
                }

                return Gate::forUser($user)->allows('delete', $page);
            },
            auditAction: 'pages.bulk.'.$action.'.web',
            entityType: 'page',
        );
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<string, mixed>
     */
    public function applyPosts(User $actor, string $action, array $ids): array
    {
        $items = Post::query()
            ->with(['translations', 'categories'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        $request = request();

        return $this->applyBulk(
            actor: $actor,
            ids: $ids,
            action: $action,
            resolve: static fn (int $id) => $items->get($id),
            execute: function (Post $post, string $resolvedAction) use ($actor, $request): void {
                if ($resolvedAction === 'unpublish') {
                    $this->postWorkflow->unpublish($post, $request, ['audit_action' => 'post.unpublish.web']);

                    return;
                }

                if ($resolvedAction === 'duplicate') {
                    $this->postContent->duplicate($post, $actor, [
                        'audit_action' => null,
                        'snapshot' => false,
                        'flush_cache' => false,
                    ]);

                    return;
                }

                if ($resolvedAction === 'delete') {
                    $this->postWorkflow->destroy($post, $request, ['audit_action' => 'post.delete.web']);
                }
            },
            allowed: static function (User $user, Post $post, string $resolvedAction): bool {
                if ($resolvedAction === 'unpublish') {
                    return Gate::forUser($user)->allows('publish', $post);
                }

                if ($resolvedAction === 'duplicate') {
                    return Gate::forUser($user)->allows('create', Post::class);
                }

                return Gate::forUser($user)->allows('delete', $post);
            },
            auditAction: 'posts.bulk.'.$action.'.web',
            entityType: 'post',
        );
    }

    /**
     * @template T of object
     *
     * @param  array<int, int>  $ids
     * @param  callable(int): (T|null)  $resolve
     * @param  callable(T, string): void  $execute
     * @param  callable(User, T, string): bool  $allowed
     * @return array<string, mixed>
     */
    private function applyBulk(
        User $actor,
        array $ids,
        string $action,
        callable $resolve,
        callable $execute,
        callable $allowed,
        string $auditAction,
        string $entityType,
    ): array {
        $successCount = 0;
        $failed = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }

            $entity = $resolve($id);
            if (! $entity) {
                $failed[] = '#'.$id.': not found';

                continue;
            }

            if (! $allowed($actor, $entity, $action)) {
                $failed[] = '#'.$id.': forbidden';

                continue;
            }

            try {
                DB::transaction(function () use ($execute, $entity, $action): void {
                    $execute($entity, $action);
                });
                $successCount++;
            } catch (\Throwable $e) {
                $failed[] = '#'.$id.': '.$e->getMessage();
            }
        }

        if ($successCount > 0 && $action === 'duplicate') {
            $this->pageCacheService->flushAll();
        }

        $this->auditLogger->log($auditAction, null, [
            'entity_type' => $entityType,
            'requested_ids' => array_values($ids),
            'success_count' => $successCount,
            'failed_count' => count($failed),
            'errors' => $failed,
        ], request(), $actor->id);

        return [
            'success_count' => $successCount,
            'failed_count' => count($failed),
            'errors' => $failed,
        ];
    }
}
