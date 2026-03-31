<?php

namespace App\Modules\Core\Services;

use App\Models\ContentRevision;
use App\Modules\Core\Contracts\ContentRevisionServiceContract;

class RevisionService implements ContentRevisionServiceContract
{
    public function snapshot(string $entityType, int $entityId, array $payload, ?int $actorId = null, ?string $locale = null): int
    {
        $revision = ContentRevision::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'locale' => $locale,
            'actor_id' => $actorId,
            'payload' => $payload,
        ]);

        $limit = max(1, (int) config('cms.revision_limit', 10));

        $allIds = ContentRevision::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('id')
            ->pluck('id');

        $obsoleteIds = $allIds->slice($limit)->values()->all();

        if ($obsoleteIds !== []) {
            ContentRevision::query()->whereIn('id', $obsoleteIds)->delete();
        }

        return $revision->id;
    }
}
