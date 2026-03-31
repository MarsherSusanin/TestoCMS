<?php

namespace App\Modules\Core\Contracts;

interface SeoResolverContract
{
    /**
     * @param array<string, mixed> $fallback
     *
     * @return array<string, mixed>
     */
    public function resolve(string $entityType, int $entityId, string $locale, array $fallback = []): array;
}
