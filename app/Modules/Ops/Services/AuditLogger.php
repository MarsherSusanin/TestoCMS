<?php

namespace App\Modules\Ops\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $action, ?Model $entity = null, array $context = [], ?Request $request = null, ?int $actorId = null): void
    {
        $request ??= request();

        AuditLog::query()->create([
            'actor_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entity !== null ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $context,
        ]);
    }
}
