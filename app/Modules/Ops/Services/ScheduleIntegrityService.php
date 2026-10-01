<?php

namespace App\Modules\Ops\Services;

use App\Modules\Caching\Services\PublicContentVersionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only preflight works before the cancellation/generation migration. */
class ScheduleIntegrityService
{
    /** @return array{errors:list<string>,warnings:list<string>} */
    public function check(): array
    {
        $report = ['errors' => [], 'warnings' => []];
        if (! Schema::hasTable('publish_schedules')) {
            return $report;
        }
        $groups = $this->groups();
        foreach ($groups as $key => $rows) {
            [$type, $id] = explode(':', $key);
            if (! in_array($type, ['page', 'post'], true)) {
                $report['errors'][] = 'Unsupported entity type for schedule IDs '.implode(',', array_column($rows, 'id')).'.';

                continue;
            }
            $entity = DB::table($type === 'page' ? 'pages' : 'posts')->where('id', $id)->first();
            if ($entity === null) {
                $report['warnings'][] = 'Orphan schedules for '.$key.': IDs '.implode(',', array_column($rows, 'id')).'; will cancel without publishing.';

                continue;
            }
            $latest = [];
            foreach ($rows as $row) {
                if (! in_array($row->action, ['publish', 'unpublish'], true)) {
                    $report['errors'][] = 'Invalid action in schedule #'.$row->id.'.';

                    continue;
                }
                if (isset($latest[$row->action])) {
                    $report['warnings'][] = 'Duplicate '.$row->action.' schedule #'.$row->id.' superseded by #'.$latest[$row->action]->id.'.';
                } else {
                    $latest[$row->action] = $row;
                }
            }
            $publish = $latest['publish'] ?? null;
            $unpublish = $latest['unpublish'] ?? null;
            if ($publish !== null && $unpublish !== null && Carbon::parse($publish->due_at)->gte(Carbon::parse($unpublish->due_at))) {
                $report['errors'][] = 'Contradictory window '.$key.': publish #'.$publish->id.' must be before unpublish #'.$unpublish->id.'.';
            }
            if ($entity->status === 'scheduled' && $publish === null) {
                $report['warnings'][] = 'Admin attention required: '.$key.' is legacy scheduled without a publish task; no automatic republish (schedule IDs '.implode(',', array_column($rows, 'id')).').';
            } elseif ($unpublish !== null && $publish === null && $entity->status !== 'published') {
                $report['errors'][] = 'Hidden '.$key.' has unpublish #'.$unpublish->id.' without an earlier publish task.';
            }
        }
        // Scheduled entities without any task must also be exposed rather than made live.
        foreach (['page' => 'pages', 'post' => 'posts'] as $type => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->where('status', 'scheduled')->pluck('id') as $id) {
                if (! isset($groups[$type.':'.$id])) {
                    $report['warnings'][] = 'Admin attention required: '.$type.':'.$id.' is scheduled without pending tasks; remains hidden.';
                }
            }
        }

        return $report;
    }

    public function assertValid(): void
    {
        $report = $this->check();
        if ($report['errors'] !== []) {
            throw new \RuntimeException('Publication schedule preflight failed. '.implode(' ', $report['errors']).' Resolve these schedule IDs before updating.');
        }
    }

    /** Migration only, after assertValid() and adding cancellation columns. */
    public function normalizeLegacy(): void
    {
        $this->assertValid();
        DB::transaction(function (): void {
            foreach ($this->groups() as $key => $rows) {
                [$type, $id] = explode(':', $key);
                $table = $type === 'page' ? 'pages' : 'posts';
                $entity = DB::table($table)->where('id', $id)->lockForUpdate()->first();
                $seen = [];
                foreach ($rows as $row) {
                    $reason = $entity === null ? 'legacy-orphaned' : (isset($seen[$row->action]) ? 'legacy-superseded' : null);
                    if ($reason !== null) {
                        DB::table('publish_schedules')->where('id', $row->id)->update(['cancelled_at' => now(), 'cancellation_reason' => $reason, 'pending_slot' => null, 'updated_at' => now()]);
                    } else {
                        $seen[$row->action] = true;
                        DB::table('publish_schedules')->where('id', $row->id)->update(['pending_slot' => 1]);
                    }
                }
                if ($entity !== null && $entity->status === 'scheduled' && isset($seen['publish'])) {
                    DB::table($table)->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
                }
            }
            app(PublicContentVersionService::class)->bump();
        });
    }

    /** Latest created_at,id wins independently for publish and unpublish. */
    private function groups(): array
    {
        $query = DB::table('publish_schedules')->whereNull('executed_at');
        if (Schema::hasColumn('publish_schedules', 'cancelled_at')) {
            $query->whereNull('cancelled_at');
        }
        $groups = [];
        foreach ($query->orderByDesc('created_at')->orderByDesc('id')->get() as $row) {
            $groups[$row->entity_type.':'.$row->entity_id][] = $row;
        }

        return $groups;
    }
}
