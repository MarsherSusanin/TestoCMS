<?php

namespace App\Modules\Updates\Services;

use App\Models\ThemeSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class CoreUpdateSettingsService
{
    private const SETTINGS_KEY = 'core_updates';
    private const STATE_KEY = 'core_update_state';

    private ?array $resolvedCache = null;
    private ?array $stateCache = null;
    private ?bool $themeTableExistsCache = null;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'current_version' => (string) config('updates.current_version', '1.0.0'),
            'channel' => (string) config('updates.default_channel', 'stable'),
            'mode' => (string) config('updates.mode', 'auto'),
            'server_url' => (string) config('updates.server_url', ''),
            'public_key' => (string) config('updates.public_key', ''),
            'deploy_hook_url' => (string) config('updates.deploy_hook_url', ''),
            'deploy_hook_token' => (string) config('updates.deploy_hook_token', ''),
            'backup_retention' => (int) config('updates.backup_retention', 5),
            'http_timeout' => (int) config('updates.http_timeout', 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolved(): array
    {
        if ($this->resolvedCache !== null) {
            return $this->resolvedCache;
        }

        $stored = $this->storedPayload(self::SETTINGS_KEY);

        return $this->resolvedCache = $this->normalizeForSave($stored);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function normalizeForSave(array $input): array
    {
        $defaults = $this->defaults();
        $mode = strtolower(trim((string) ($input['mode'] ?? $defaults['mode'])));
        if (! in_array($mode, ['auto', 'filesystem-updater', 'deploy-hook'], true)) {
            $mode = (string) $defaults['mode'];
        }

        $channel = strtolower(trim((string) ($input['channel'] ?? $defaults['channel'])));
        if ($channel === '') {
            $channel = (string) $defaults['channel'];
        }

        $backupRetention = (int) ($input['backup_retention'] ?? $defaults['backup_retention']);
        if ($backupRetention < 1) {
            $backupRetention = 1;
        }
        if ($backupRetention > 30) {
            $backupRetention = 30;
        }

        $httpTimeout = (int) ($input['http_timeout'] ?? $defaults['http_timeout']);
        if ($httpTimeout < 3) {
            $httpTimeout = 3;
        }
        if ($httpTimeout > 120) {
            $httpTimeout = 120;
        }

        return [
            'current_version' => trim((string) ($input['current_version'] ?? $defaults['current_version'])),
            'channel' => $channel,
            'mode' => $mode,
            'server_url' => rtrim(trim((string) ($input['server_url'] ?? $defaults['server_url'])), '/'),
            'public_key' => trim((string) ($input['public_key'] ?? $defaults['public_key'])),
            'deploy_hook_url' => trim((string) ($input['deploy_hook_url'] ?? $defaults['deploy_hook_url'])),
            'deploy_hook_token' => trim((string) ($input['deploy_hook_token'] ?? $defaults['deploy_hook_token'])),
            'backup_retention' => $backupRetention,
            'http_timeout' => $httpTimeout,
        ];
    }

    public function save(array $payload, ?int $actorId = null): ?ThemeSetting
    {
        if (! $this->themeSettingsTableExists()) {
            return null;
        }

        $record = ThemeSetting::query()->updateOrCreate(
            ['key' => self::SETTINGS_KEY],
            [
                'settings' => $this->normalizeForSave($payload),
                'updated_by' => $actorId,
            ]
        );

        $this->resolvedCache = null;

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        if ($this->stateCache !== null) {
            return $this->stateCache;
        }

        $raw = $this->storedPayload(self::STATE_KEY);

        return $this->stateCache = [
            'last_check_at' => (string) ($raw['last_check_at'] ?? ''),
            'available_release' => is_array($raw['available_release'] ?? null) ? $raw['available_release'] : null,
            'pending_package' => is_array($raw['pending_package'] ?? null) ? $raw['pending_package'] : null,
            'installed_version' => trim((string) ($raw['installed_version'] ?? '')),
            'last_apply_at' => (string) ($raw['last_apply_at'] ?? ''),
            'last_error' => trim((string) ($raw['last_error'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function saveState(array $state, ?int $actorId = null): ?ThemeSetting
    {
        if (! $this->themeSettingsTableExists()) {
            return null;
        }

        $record = ThemeSetting::query()->updateOrCreate(
            ['key' => self::STATE_KEY],
            [
                'settings' => $state,
                'updated_by' => $actorId,
            ]
        );

        $this->stateCache = null;

        return $record;
    }

    public function installedVersion(): string
    {
        $stateVersion = trim((string) ($this->state()['installed_version'] ?? ''));
        if ($stateVersion !== '') {
            return $stateVersion;
        }

        return (string) $this->resolved()['current_version'];
    }

    public function setInstalledVersion(string $version, ?int $actorId = null): void
    {
        $state = $this->state();
        $state['installed_version'] = trim($version);
        $this->saveState($state, $actorId);
    }

    public function setLastError(?string $error, ?int $actorId = null): void
    {
        $state = $this->state();
        $state['last_error'] = trim((string) ($error ?? ''));
        $this->saveState($state, $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedPayload(string $key): array
    {
        if (! $this->themeSettingsTableExists()) {
            return [];
        }

        $record = ThemeSetting::query()->where('key', $key)->first();
        $payload = $record?->settings;

        return is_array($payload) ? $payload : [];
    }

    private function themeSettingsTableExists(): bool
    {
        if ($this->themeTableExistsCache !== null) {
            return $this->themeTableExistsCache;
        }

        try {
            $this->themeTableExistsCache = Schema::hasTable('theme_settings');
        } catch (QueryException) {
            $this->themeTableExistsCache = false;
        }

        return $this->themeTableExistsCache;
    }
}
