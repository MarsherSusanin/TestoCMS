<?php

namespace App\Modules\Updates\Services;

use App\Models\CoreBackup;
use App\Models\CoreUpdateLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class CoreUpdateService
{
    public function __construct(
        private readonly CoreUpdateSettingsService $settings,
        private readonly CoreUpdateEnvironment $environment,
        private readonly UpdateManifestClient $manifestClient,
        private readonly UpdatePreflightService $preflight,
        private readonly CorePackageApplier $packageApplier,
        private readonly DeployHookUpdateDriver $deployHookDriver,
        private readonly FilesystemUpdateDriver $filesystemDriver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboardSnapshot(): array
    {
        $state = $this->settings->state();
        $available = is_array($state['available_release'] ?? null) ? $state['available_release'] : null;

        return [
            'current_version' => $this->settings->installedVersion(),
            'settings' => $this->settings->resolved(),
            'state' => $state,
            'has_available_update' => $available !== null
                && isset($available['version'])
                && version_compare((string) $available['version'], $this->settings->installedVersion(), '>'),
            'available_version' => $available['version'] ?? null,
            'execution_mode' => $this->environment->resolveExecutionMode(),
        ];
    }

    /**
     * @return array<int, CoreUpdateLog>
     */
    public function latestLogs(int $limit = 60): array
    {
        return CoreUpdateLog::query()
            ->orderByDesc('id')
            ->limit(max(1, min(300, $limit)))
            ->get()
            ->all();
    }

    /**
     * @return array<int, CoreBackup>
     */
    public function latestBackups(int $limit = 20): array
    {
        return CoreBackup::query()
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveSettings(array $input, ?int $actorId = null): void
    {
        $payload = $this->settings->normalizeForSave($input);
        $this->settings->save($payload, $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkRemote(?int $actorId = null): array
    {
        $manifest = $this->manifestClient->fetchRemoteManifest();
        $version = trim((string) ($manifest['version'] ?? ''));
        $current = $this->settings->installedVersion();
        $isNewer = version_compare($version, $current, '>');

        $state = $this->settings->state();
        $state['last_check_at'] = now()->toIso8601String();
        $state['available_release'] = $isNewer ? $manifest : null;
        $state['last_error'] = '';
        $this->settings->saveState($state, $actorId);

        $resolved = $this->settings->resolved();
        $serverUrl = trim((string) ($resolved['server_url'] ?? ''));
        $manifestUrl = rtrim($serverUrl, '/').(string) config('updates.manifest_endpoint', '/api/updates/manifest');

        $this->logAction(
            action: 'check',
            status: 'success',
            fromVersion: $current,
            toVersion: $version,
            message: $isNewer ? 'Update found.' : 'No updates available.',
            context: [
                'manifest_url' => $manifestUrl,
                'channel' => (string) ($manifest['channel'] ?? ''),
            ],
            actorId: $actorId,
        );

        return [
            'manifest' => $manifest,
            'is_newer' => $isNewer,
            'current_version' => $current,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadManualPackage(UploadedFile $zipFile, ?int $actorId = null): array
    {
        $packagesRoot = $this->environment->storageRoot().DIRECTORY_SEPARATOR.'packages';
        File::ensureDirectoryExists($packagesRoot);

        $targetPath = $packagesRoot.DIRECTORY_SEPARATOR.'manual_'.now()->format('Ymd_His').'_'.Str::random(8).'.zip';
        File::copy($zipFile->getRealPath(), $targetPath);

        $inspection = $this->packageApplier->inspectArchive($targetPath);
        $release = $inspection['release'];
        $hash = hash_file('sha256', $targetPath);

        $state = $this->settings->state();
        $state['pending_package'] = [
            'source' => 'manual',
            'version' => (string) $release['version'],
            'zip_path' => $targetPath,
            'sha256' => $hash,
            'release' => $release,
            'uploaded_at' => now()->toIso8601String(),
        ];
        $state['last_error'] = '';
        $this->settings->saveState($state, $actorId);

        $this->logAction(
            action: 'upload',
            status: 'success',
            fromVersion: $this->settings->installedVersion(),
            toVersion: (string) $release['version'],
            message: 'Manual package uploaded.',
            context: [
                'zip_path' => $targetPath,
                'sha256' => $hash,
            ],
            actorId: $actorId,
        );

        return [
            'version' => (string) $release['version'],
            'zip_path' => $targetPath,
            'sha256' => $hash,
            'release' => $release,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applyUpdate(?int $actorId = null): array
    {
        $mode = $this->environment->resolveExecutionMode();

        if ($mode === 'deploy-hook') {
            $state = $this->settings->state();
            $pending = is_array($state['pending_package'] ?? null) ? $state['pending_package'] : null;
            $available = is_array($state['available_release'] ?? null) ? $state['available_release'] : null;
            $result = $this->deployHookDriver->apply($pending, $available, $actorId);

            $this->logAction(
                action: 'apply',
                status: 'success',
                fromVersion: $this->settings->installedVersion(),
                toVersion: (string) ($result['target_version'] ?? ''),
                message: 'Deploy hook accepted update request.',
                context: [
                    'mode' => 'deploy-hook',
                    'http_status' => (int) ($result['http_status'] ?? 0),
                ],
                actorId: $actorId,
            );

            return $result;
        }

        $package = $this->resolvePackageForApply($actorId);
        $targetVersion = trim((string) ($package['version'] ?? ''));

        try {
            $result = $this->filesystemDriver->apply($package, $actorId);

            $this->logAction(
                action: 'apply',
                status: 'success',
                fromVersion: (string) ($result['from_version'] ?? $this->settings->installedVersion()),
                toVersion: $targetVersion,
                message: 'Core update applied.',
                context: [
                    'mode' => 'filesystem-updater',
                    'backup_key' => (string) ($result['backup_key'] ?? ''),
                    'preflight' => $result['preflight'] ?? [],
                ],
                actorId: $actorId,
            );

            return $result;
        } catch (\Throwable $e) {
            $this->logAction(
                action: 'apply',
                status: 'failed',
                fromVersion: $this->settings->installedVersion(),
                toVersion: $targetVersion !== '' ? $targetVersion : null,
                message: $e->getMessage(),
                context: [
                    'mode' => 'filesystem-updater',
                ],
                actorId: $actorId,
            );

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rollbackBackup(CoreBackup $backup, ?int $actorId = null): array
    {
        $result = $this->filesystemDriver->rollback($backup, $actorId, false, false);

        $this->logAction(
            action: 'rollback',
            status: 'success',
            fromVersion: (string) $backup->to_version,
            toVersion: (string) $backup->from_version,
            message: 'Rollback completed.',
            context: [
                'backup_key' => $backup->backup_key,
                'trigger' => 'manual',
            ],
            actorId: $actorId,
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function runPreflight(string $targetVersion, array $package): array
    {
        return $this->preflight->run($targetVersion, $package);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePackageForApply(?int $actorId = null): array
    {
        $state = $this->settings->state();
        $pending = is_array($state['pending_package'] ?? null) ? $state['pending_package'] : null;
        if ($pending !== null && is_file((string) ($pending['zip_path'] ?? ''))) {
            return $pending;
        }

        $manifest = is_array($state['available_release'] ?? null) ? $state['available_release'] : null;
        if ($manifest === null) {
            throw new RuntimeException('No update package available. Run update check or upload package first.');
        }

        $package = $this->manifestClient->downloadCloudPackage($manifest);
        $state['pending_package'] = $package;
        $state['last_error'] = '';
        $this->settings->saveState($state, $actorId);

        return $package;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logAction(
        string $action,
        string $status,
        ?string $fromVersion,
        ?string $toVersion,
        ?string $message,
        array $context,
        ?int $actorId
    ): void {
        CoreUpdateLog::query()->create([
            'action' => $action,
            'status' => $status,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'message' => $message,
            'context' => $context,
            'actor_id' => $actorId,
            'created_at' => now(),
        ]);
    }
}
