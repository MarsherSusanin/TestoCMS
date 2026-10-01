<?php

namespace App\Modules\Content\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\PublishSchedule;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Caching\Services\PublicContentVersionService;
use App\Modules\Ops\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One state machine for manual actions, CRUD status edits and scheduler jobs. */
class PublicationTransitionService
{
    public function __construct(
        private readonly PublicContentVersionService $versions,
        private readonly PageCacheService $cache,
        private readonly ContentPublicationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    public function entityType(Page|Post $entity): string
    {
        return $entity instanceof Page ? 'page' : 'post';
    }

    public function assertScheduledState(Page|Post $entity): void
    {
        if (! $this->pending($entity)->where('action', 'publish')->exists()) {
            throw ValidationException::withMessages(['status' => 'Scheduled status requires an active publish schedule.']);
        }
    }

    public function cancelPending(Page|Post $entity, string $reason = 'manual-status-change', ?int $actorId = null, ?string $action = null): int
    {
        $query = $this->pending($entity);
        if ($action !== null) {
            $query->where('action', $action);
        }

        return $query->update(['cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancellation_reason' => $reason, 'pending_slot' => null, 'updated_at' => now()]);
    }

    /** Caller holds the entity lock in its mutation transaction. */
    public function reconcileStatusChange(Page|Post $entity, string $oldStatus, ?int $actorId = null, bool $force = false): void
    {
        if ($oldStatus === $entity->status && ! $force) {
            return;
        }
        if ($entity->status === 'published') {
            $this->cancelPending($entity, 'manual-publish', $actorId, 'publish');
            // Expired unpublishes must not unexpectedly undo a fresh manual publication.
            $this->pending($entity)->where('action', 'unpublish')->where('due_at', '<=', now())
                ->update(['cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancellation_reason' => 'expired-before-manual-publish', 'pending_slot' => null, 'updated_at' => now()]);
        } elseif (in_array($entity->status, ['draft', 'review', 'archived'], true)) {
            $this->cancelPending($entity, 'manual-'.$entity->status, $actorId);
        } elseif ($entity->status === 'scheduled') {
            $this->assertScheduledState($entity);
        }
    }

    /** @template T of Page|Post
     * @param  T  $entity
     * @return T
     */
    public function transition(Page|Post $entity, string $action, Request $request, array $context = []): Page|Post
    {
        if (! in_array($action, ['publish', 'unpublish'], true)) {
            throw new \InvalidArgumentException('Invalid publication action.');
        }

        return DB::transaction(function () use ($entity, $action, $request, $context): Page|Post {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $actor = $request->user();
            abort_unless($actor !== null, 403);
            $this->guard->assertCanPublish($actor, $this->entityType($locked), $locked);
            $oldStatus = (string) $locked->status;
            $this->applyState($locked, $action);
            // Repeated manual actions still cancel superseded pending tasks.
            $this->reconcileStatusChange($locked, $oldStatus, $actor->id, true);
            $this->versions->bump();
            $type = $this->entityType($locked);
            $this->audit->log((string) ($context['audit_action'] ?? $type.'.'.$action.'.web'), $locked, (array) ($context['audit_context'] ?? []), $request, $actor->id);

            return $locked->load('translations');
        }, 3);
    }

    public function destroy(Page|Post $entity, Request $request, array $context = []): void
    {
        DB::transaction(function () use ($entity, $request, $context): void {
            app(ContentMutationGuard::class)->lockMedia();
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $actor = $request->user();
            abort_unless($actor !== null, 403);
            $this->guard->assertCanDelete($actor, $this->entityType($locked), $locked);
            $type = $this->entityType($locked);
            $this->cancelPending($locked, 'entity-deleted', $actor->id);
            $locked->delete();
            $this->versions->bump();
            $this->audit->log((string) ($context['audit_action'] ?? $type.'.delete.web'), $locked, (array) ($context['audit_context'] ?? []), $request, $actor->id);
        }, 3);
        $this->cache->flushAll(false);
    }

    public function schedule(Page|Post $entity, string $action, string $dueAt, Request $request, array $context = []): PublishSchedule
    {
        if (! in_array($action, ['publish', 'unpublish'], true)) {
            throw ValidationException::withMessages(['action' => 'Select publish or unpublish.']);
        }
        try {
            $due = Carbon::parse($dueAt, config('app.timezone'))->setTimezone(config('app.timezone'));
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['due_at' => 'Invalid publication date.']);
        }
        if (! $due->isFuture()) {
            throw ValidationException::withMessages(['due_at' => 'The schedule must be in the future.']);
        }

        return DB::transaction(function () use ($entity, $action, $due, $request, $context): PublishSchedule {
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $actor = $request->user();
            abort_unless($actor !== null, 403);
            $type = $this->entityType($locked);
            $this->guard->assertCanPublish($actor, $type, $locked);
            $opposite = $this->pending($locked)->where('action', $action === 'publish' ? 'unpublish' : 'publish')->first();
            if ($opposite !== null) {
                $publishAt = $action === 'publish' ? $due : $opposite->due_at;
                $unpublishAt = $action === 'unpublish' ? $due : $opposite->due_at;
                if (! $publishAt->lt($unpublishAt)) {
                    throw ValidationException::withMessages(['due_at' => 'Publish must occur before unpublish. Existing schedule #'.$opposite->id.'.']);
                }
            } elseif ($action === 'unpublish' && ($locked->status !== 'published' || $locked->published_at === null || $locked->published_at->isFuture())) {
                throw ValidationException::withMessages(['action' => 'A hidden material requires an earlier publish schedule before scheduling unpublish.']);
            }
            $this->cancelPending($locked, 'rescheduled', $actor->id, $action);
            $schedule = PublishSchedule::query()->create(['entity_type' => $type, 'entity_id' => $locked->id, 'action' => $action, 'due_at' => $due, 'created_by' => $actor->id]);
            // Scheduling records intent; only execution changes material status.
            $this->versions->bump();
            $auditContext = (array) ($context['audit_context'] ?? ['action' => $action, 'due_at' => $due->toIso8601String()]);
            $auditContext['schedule_id'] = $schedule->id;
            $this->audit->log((string) ($context['audit_action'] ?? $type.'.schedule.web'), $locked, $auditContext, $request, $actor->id);

            return $schedule;
        }, 3);
    }

    public function cancel(Page|Post $entity, int $scheduleId, Request $request): PublishSchedule
    {
        return DB::transaction(function () use ($entity, $scheduleId, $request): PublishSchedule {
            // Same entity->job ordering is used by scheduler and all mutations.
            $locked = $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $actor = $request->user();
            abort_unless($actor !== null, 403);
            $type = $this->entityType($locked);
            $this->guard->assertCanPublish($actor, $type, $locked);
            $job = PublishSchedule::query()->whereKey($scheduleId)->where('entity_type', $type)->where('entity_id', $locked->id)->lockForUpdate()->firstOrFail();
            if ($job->executed_at === null && $job->cancelled_at === null) {
                $job->update(['cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => 'manual-cancel']);
                if ($job->action === 'publish' && $locked->status !== 'published') {
                    // Its dependent unpublish is no longer a valid window.
                    $this->cancelPending($locked, 'publish-window-cancelled', $actor->id, 'unpublish');
                    if ($locked->status === 'scheduled') {
                        $locked->update(['status' => 'draft']);
                    }
                }
                $this->versions->bump();
                $this->audit->log($type.'.schedule.cancel', $locked, ['schedule_id' => $job->id], $request, $actor->id);
            }

            return $job;
        }, 3);
    }

    /** Called only after the scheduler locks entity then due job and rechecks pending. */
    public function executeScheduled(Page|Post $lockedEntity, PublishSchedule $lockedJob): void
    {
        if ($lockedJob->executed_at !== null || $lockedJob->cancelled_at !== null || $lockedJob->due_at->isFuture()) {
            return;
        }
        if (! in_array($lockedJob->action, ['publish', 'unpublish'], true)) {
            throw new \RuntimeException('Invalid action for schedule #'.$lockedJob->id.'. Run cms:schedules:check.');
        }
        $this->applyState($lockedEntity, $lockedJob->action);
        $lockedJob->update(['executed_at' => now()]);
        $this->versions->bump();
        $this->audit->log('scheduler.'.$lockedJob->action, $lockedEntity, ['schedule_id' => $lockedJob->id], null, $lockedJob->created_by);
    }

    private function applyState(Page|Post $entity, string $action): void
    {
        $entity->status = $action === 'publish' ? 'published' : 'draft';
        if ($action === 'publish') {
            $entity->published_at = now();
            $entity->archived_at = null;
        }
        $entity->save();
    }

    /** @return Builder<PublishSchedule> */
    private function pending(Page|Post $entity): Builder
    {
        return PublishSchedule::query()->pending()->where('entity_type', $this->entityType($entity))->where('entity_id', $entity->id);
    }
}
