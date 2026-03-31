<?php

namespace App\Modules\Ops\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\PublishSchedule;
use App\Modules\Caching\Services\PageCacheService;
use Illuminate\Support\Facades\DB;

class PublishSchedulerService
{
    public function __construct(
        private readonly PageCacheService $pageCacheService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function runDue(): int
    {
        $count = 0;

        $schedules = PublishSchedule::query()
            ->whereNull('executed_at')
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->limit(100)
            ->get();

        foreach ($schedules as $schedule) {
            DB::transaction(function () use ($schedule, &$count): void {
                $entityType = strtolower($schedule->entity_type);
                $entityId = (int) $schedule->entity_id;
                $action = strtolower($schedule->action);

                if ($entityType === 'post') {
                    $post = Post::query()->find($entityId);
                    if ($post !== null) {
                        if ($action === 'publish') {
                            $post->status = 'published';
                            $post->published_at = now();
                        } else {
                            $post->status = 'draft';
                        }
                        $post->save();
                        $this->auditLogger->log('scheduler.'.$action, $post, ['schedule_id' => $schedule->id]);
                    }
                }

                if ($entityType === 'page') {
                    $page = Page::query()->find($entityId);
                    if ($page !== null) {
                        if ($action === 'publish') {
                            $page->status = 'published';
                            $page->published_at = now();
                        } else {
                            $page->status = 'draft';
                        }
                        $page->save();
                        $this->auditLogger->log('scheduler.'.$action, $page, ['schedule_id' => $schedule->id]);
                    }
                }

                $schedule->executed_at = now();
                $schedule->save();
                $count++;
            });
        }

        if ($count > 0) {
            $this->pageCacheService->flushAll();
        }

        return $count;
    }
}
