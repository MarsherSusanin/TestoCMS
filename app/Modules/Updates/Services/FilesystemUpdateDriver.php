<?php

namespace App\Modules\Updates\Services;

use App\Models\CoreBackup;
use RuntimeException;

class FilesystemUpdateDriver
{
    public function __construct(
        private readonly CoreUpdateSettingsService $settings,
        private readonly UpdatePreflightService $preflight,
        private readonly CoreBackupService $backupService,
        private readonly CorePackageApplier $packageApplier,
        private readonly CoreUpdateHealthCheckService $healthChecks,
    ) {}

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    public function apply(array $package, ?int $actorId = null): array
    {
        $targetVersion = trim((string) ($package['version'] ?? ''));
        if ($targetVersion === '') {
            throw new RuntimeException('Cannot determine target version for update package.');
        }

        $preflight = $this->preflight->run($targetVersion, $package);
        if (! ($preflight['ok'] ?? false)) {
            throw new RuntimeException('Preflight failed: '.implode(' | ', $preflight['issues'] ?? []));
        }

        $backup = $this->backupService->createBackup($this->settings->installedVersion(), $targetVersion, $actorId);
        $maintenanceDown = false;

        try {
            $maintenanceDown = $this->healthChecks->artisanCall('down', ['--retry' => 60], false);
            $this->packageApplier->applyArchiveToFilesystem((string) $package['zip_path']);
            $this->healthChecks->artisanCall('migrate', ['--force' => true], true);
            $this->healthChecks->artisanCall('optimize:clear', [], false);
            $this->healthChecks->artisanCall('cms:modules:cache', [], false);
            $this->healthChecks->runHealthCheck();

            $backup->forceFill([
                'status' => 'applied',
                'restore_status' => 'not_required',
                'last_error' => null,
            ])->save();

            $state = $this->settings->state();
            $state['installed_version'] = $targetVersion;
            $state['last_apply_at'] = now()->toIso8601String();
            $state['last_error'] = '';
            $state['pending_package'] = null;
            if (is_array($state['available_release'] ?? null) && (string) ($state['available_release']['version'] ?? '') === $targetVersion) {
                $state['available_release'] = null;
            }
            $this->settings->saveState($state, $actorId);

            return [
                'mode' => 'filesystem-updater',
                'status' => 'success',
                'target_version' => $targetVersion,
                'backup_key' => (string) $backup->backup_key,
                'preflight' => $preflight,
                'from_version' => (string) $backup->from_version,
            ];
        } catch (\Throwable $e) {
            $this->settings->setLastError($e->getMessage(), $actorId);

            try {
                $this->rollback($backup, $actorId, true, true);
            } catch (\Throwable $rollbackError) {
                $backup->forceFill([
                    'status' => 'failed',
                    'restore_status' => 'failed',
                    'last_error' => $rollbackError->getMessage(),
                ])->save();
            }

            throw $e;
        } finally {
            if ($maintenanceDown) {
                $this->healthChecks->artisanCall('up', [], false);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback(CoreBackup $backup, ?int $actorId = null, bool $isAutoRollback = false, bool $alreadyInMaintenance = false): array
    {
        $maintenanceDown = false;

        try {
            if (! $alreadyInMaintenance) {
                $maintenanceDown = $this->healthChecks->artisanCall('down', ['--retry' => 60], false);
            }

            $this->backupService->restoreSnapshot($backup);
            $this->healthChecks->artisanCall('optimize:clear', [], false);

            $backup->forceFill([
                'status' => 'rolled_back',
                'restore_status' => $isAutoRollback ? 'auto' : 'manual',
                'last_error' => null,
            ])->save();

            $state = $this->settings->state();
            if (trim((string) $backup->from_version) !== '') {
                $state['installed_version'] = (string) $backup->from_version;
            }
            $state['last_error'] = '';
            $this->settings->saveState($state, $actorId);

            return [
                'status' => 'success',
                'backup_key' => $backup->backup_key,
                'restored_version' => (string) $backup->from_version,
                'auto' => $isAutoRollback,
            ];
        } catch (\Throwable $e) {
            $backup->forceFill([
                'status' => 'failed',
                'restore_status' => 'failed',
                'last_error' => $e->getMessage(),
            ])->save();

            throw $e;
        } finally {
            if ($maintenanceDown) {
                $this->healthChecks->artisanCall('up', [], false);
            }
        }
    }
}
