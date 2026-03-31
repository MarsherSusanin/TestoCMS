<?php

namespace App\Modules\Core\Contracts;

interface ContentRevisionServiceContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function snapshot(string $entityType, int $entityId, array $payload, ?int $actorId = null, ?string $locale = null): int;
}
