<?php

namespace App\Modules\Core\Services;

use App\Models\ThemeSetting;
use App\Modules\Caching\Services\PublicContentVersionService;
use App\Modules\Content\Services\AssetUsageService;
use App\Modules\Content\Services\ContentMutationGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteChromeSettingStore
{
    private ?bool $tableExistsCache = null;

    /**
     * @return array<string, mixed>
     */
    public function loadPayload(): array
    {
        if (! $this->themeTableExists()) {
            return [];
        }

        try {
            $record = ThemeSetting::query()->where('key', 'site_chrome')->first();

            return is_array($record?->settings) ? $record->settings : [];
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function savePayload(array $payload, ?int $actorId = null): ThemeSetting
    {
        return DB::transaction(function () use ($payload, $actorId): ThemeSetting {
            app(ContentMutationGuard::class)->lockMedia();
            app(AssetUsageService::class)->assertReferencesAvailable($payload);
            $record = ThemeSetting::query()->updateOrCreate(
                ['key' => 'site_chrome'],
                ['settings' => $payload, 'updated_by' => $actorId],
            );
            app(PublicContentVersionService::class)->bump();

            return $record;
        });
    }

    private function themeTableExists(): bool
    {
        if ($this->tableExistsCache !== null) {
            return $this->tableExistsCache;
        }

        try {
            $this->tableExistsCache = Schema::hasTable('theme_settings');
        } catch (\Throwable) {
            $this->tableExistsCache = false;
        }

        return $this->tableExistsCache;
    }
}
