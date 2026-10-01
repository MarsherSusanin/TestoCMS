<?php

namespace App\Modules\Ops\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\PublishSchedule;
use App\Modules\Content\Services\PublicationTransitionService;
use App\Modules\Updates\Services\UpdateOperationGate;
use Illuminate\Support\Facades\DB;

class PublishSchedulerService
{
    public function __construct(private readonly PublicationTransitionService $transitions) {}

    public function runDue(): int
    {
        $gate = app(UpdateOperationGate::class);
        if (app()->isDownForMaintenance() || ! $gate->acquire()) {
            return 0;
        }
        try {
            return $this->processDue();
        } finally {
            $gate->release();
        }
    }

    private function processDue(): int
    {
        $ids = PublishSchedule::query()->pending()->where('due_at', '<=', now())->orderBy('due_at')->orderBy('id')->limit(100)->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $processed = DB::transaction(function () use ($id): bool {
                // Read identity only, then acquire entity before job everywhere.
                $identity = PublishSchedule::query()->find($id);
                if ($identity === null) {
                    return false;
                }
                $class = match ($identity->entity_type) {
                    'page' => Page::class,
                    'post' => Post::class,
                    default => null,
                };
                if ($class === null) {
                    throw new \RuntimeException('Invalid entity for schedule #'.$id.'. Run cms:schedules:check.');
                }
                $entity = $class::query()->whereKey($identity->entity_id)->lockForUpdate()->first();
                $job = PublishSchedule::query()->whereKey($id)->lockForUpdate()->first();
                if ($job === null || $job->executed_at !== null || $job->cancelled_at !== null || $job->due_at->isFuture()) {
                    return false;
                }
                if ($entity === null) {
                    $job->update(['cancelled_at' => now(), 'cancellation_reason' => 'entity-missing']);

                    return false;
                }
                $this->transitions->executeScheduled($entity, $job);

                return true;
            }, 3);
            $count += $processed ? 1 : 0;
        }

        return $count;
    }
}
