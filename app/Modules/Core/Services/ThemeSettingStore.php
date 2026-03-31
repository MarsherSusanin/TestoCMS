<?php

namespace App\Modules\Core\Services;

use App\Models\ThemeSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class ThemeSettingStore
{
    private ?bool $tableExistsCache = null;

    /**
     * @return array<string, mixed>
     */
    public function loadDefaultPayload(): array
    {
        if (! $this->themeTableExists()) {
            return [];
        }

        try {
            $record = ThemeSetting::query()->where('key', 'default')->first();

            return is_array($record?->settings) ? $record->settings : [];
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveDefaultPayload(array $payload, ?int $actorId = null): ThemeSetting
    {
        return ThemeSetting::query()->updateOrCreate(
            ['key' => 'default'],
            [
                'settings' => $payload,
                'updated_by' => $actorId,
            ]
        );
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
