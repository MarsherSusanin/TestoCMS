<?php

namespace App\Modules\Core\Services;

use App\Models\ThemeSetting;

class SiteChromeSettingsService
{
    public function __construct(
        private readonly SiteChromeNormalizerService $normalizer,
        private readonly SiteChromeSettingStore $store,
    ) {
    }

    private ?array $cachedResolvedChrome = null;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->normalizer->defaults();
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedChrome(): array
    {
        if ($this->cachedResolvedChrome !== null) {
            return $this->cachedResolvedChrome;
        }

        return $this->cachedResolvedChrome = $this->normalizer->normalizeForSave($this->store->loadPayload());
    }

    public function searchEnabled(): bool
    {
        $resolved = $this->resolvedChrome();

        return (bool) ($resolved['search']['enabled'] ?? false);
    }

    public function searchPathSlug(): string
    {
        $resolved = $this->resolvedChrome();
        $slug = trim((string) ($resolved['search']['path_slug'] ?? 'search'), '/');

        return $slug !== '' ? $slug : 'search';
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalizeForSave(array $input): array
    {
        return $this->normalizer->normalizeForSave($input);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload, ?int $actorId = null): ThemeSetting
    {
        $record = $this->store->savePayload($payload, $actorId);
        $this->cachedResolvedChrome = null;

        return $record;
    }
}
