<?php

namespace App\Modules\Updates\Services;

use App\Models\CoreBackup;
use App\Modules\Extensibility\Services\ModulePublicAssetsPublisherService;
use App\Modules\Setup\Services\PublicMediaService;
use RuntimeException;

class FilesystemUpdateDriver
{
    public function __construct(
        private readonly CoreUpdateSettingsService $settings,
        private readonly UpdatePreflightService $preflight,
        private readonly CoreBackupService $backupService,
        private readonly CorePackageApplier $packageApplier,
        private readonly CoreUpdateHealthCheckService $healthChecks,
        private readonly ManagedPublicRootSyncService $publicRootSync,
        private readonly ModulePublicAssetsPublisherService $publicAssetsPublisher,
        private readonly UpdateOperationGate $gate,
        private readonly UpdateOperationJournal $journal,
    ) {}

    public function apply(array $package, ?int $actorId = null): array
    {
        return $this->gate->runExclusive(fn (): array => $this->applyLocked($package, $actorId));
    }

    private function applyLocked(array $package, ?int $actorId): array
    {
        $targetVersion = trim((string) ($package['version'] ?? ''));
        if ($targetVersion === '') {
            throw new RuntimeException('Cannot determine target version for update package.');
        }
        @set_time_limit(0);
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        $preflight = $this->preflight->run($targetVersion, $package);
        if (! $preflight['ok']) {
            throw new RuntimeException('Preflight failed: '.implode(' | ', $preflight['issues']));
        }

        $backup = null;
        $canResume = true;
        $maintenanceDown = false;
        try {
            $maintenanceDown = $this->healthChecks->artisanCall('down', ['--retry' => 60]);
            // Exclusive gate has drained writers BEFORE taking the database snapshot.
            $backup = $this->backupService->createBackup($this->settings->installedVersion(), $targetVersion, $actorId);
            $this->journal->write($backup->backup_path, ['phase' => 'snapshot_created', 'target_version' => $targetVersion]);
            $canResume = false;
            $this->journal->write($backup->backup_path, ['phase' => 'applying']);
            $this->packageApplier->applyArchiveToFilesystem((string) $package['zip_path']);
            $this->publicRootSync->syncFromReleaseRoot();
            $this->healthChecks->artisanCall('migrate', ['--force' => true]);
            $this->refreshRuntime();
            $health = $this->healthChecks->runHealthCheck();
            $canResume = $health['status'] === 'verified';
            $backup->forceFill(['status' => 'applied', 'restore_status' => $canResume ? 'not_required' : 'health_unverified', 'last_error' => $health['warning']])->save();
            $state = $this->settings->state();
            $state['installed_version'] = $targetVersion;
            $state['last_apply_at'] = now()->toIso8601String();
            $state['last_error'] = $health['warning'] ?? '';
            $state['pending_package'] = null;
            if (($state['available_release']['version'] ?? '') === $targetVersion) {
                $state['available_release'] = null;
            }
            $this->settings->saveState($state, $actorId);
            $this->journal->write($backup->backup_path, ['phase' => $canResume ? 'applied' : 'health_unverified', 'health' => $health]);

            return [
                'mode' => 'filesystem-updater', 'status' => $canResume ? 'success' : 'health_unverified',
                'target_version' => $targetVersion, 'backup_key' => $backup->backup_key,
                'preflight' => $preflight, 'from_version' => $backup->from_version,
                'health_warning' => $health['warning'],
            ];
        } catch (\Throwable $e) {
            if ($backup !== null && ! $canResume) {
                $this->journal->write($backup->backup_path, ['phase' => 'apply_failed', 'error' => $e->getMessage()]);
                try {
                    $rollback = $this->rollback($backup, $actorId, true, true);
                    $canResume = $rollback['status'] === 'success';
                } catch (\Throwable $rollbackError) {
                    $canResume = false;
                    $this->journal->write($backup->backup_path, ['phase' => 'restore_failed', 'restore_error' => $rollbackError->getMessage()]);
                    $this->saveBackupResult($backup, ['status' => 'failed', 'restore_status' => 'failed', 'last_error' => $rollbackError->getMessage()]);
                }
            }
            throw $e;
        } finally {
            if ($maintenanceDown && $canResume) {
                $this->healthChecks->artisanCall('up');
                $this->restartWorkers();
            }
        }
    }

    public function rollback(CoreBackup $backup, ?int $actorId = null, bool $isAutoRollback = false, bool $alreadyInMaintenance = false): array
    {
        return $this->gate->runExclusive(fn (): array => $this->rollbackLocked($backup, $actorId, $isAutoRollback, $alreadyInMaintenance));
    }

    private function rollbackLocked(CoreBackup $backup, ?int $actorId, bool $isAutoRollback, bool $alreadyInMaintenance): array
    {
        $maintenanceDown = false;
        $verified = false;
        $this->journal->write($backup->backup_path, ['phase' => 'restoring', 'backup_key' => $backup->backup_key]);
        try {
            if (! $alreadyInMaintenance) {
                $maintenanceDown = $this->healthChecks->artisanCall('down', ['--retry' => 60]);
            }
            $this->backupService->restoreSnapshot($backup);
            $healthWarning = null;
            try {
                $this->refreshRuntime();
                $health = $this->healthChecks->runHealthCheck();
                $verified = $health['status'] === 'verified';
                $healthWarning = $health['warning'];
            } catch (\Throwable $e) {
                $healthWarning = $e->getMessage();
            }
            $this->saveBackupResult($backup, [
                'status' => 'rolled_back',
                'restore_status' => $verified ? ($isAutoRollback ? 'auto' : 'manual') : 'health_unverified',
                'last_error' => $healthWarning,
            ]);
            $state = $this->settings->state();
            $state['installed_version'] = $backup->from_version;
            $state['last_error'] = $healthWarning ?? '';
            $this->settings->saveState($state, $actorId);
            $this->journal->write($backup->backup_path, ['phase' => $verified ? 'restored' : 'health_unverified', 'restored_version' => $backup->from_version, 'health_warning' => $healthWarning]);

            return [
                'status' => $verified ? 'success' : 'health_unverified', 'backup_key' => $backup->backup_key,
                'restored_version' => $backup->from_version, 'auto' => $isAutoRollback, 'health_warning' => $healthWarning,
            ];
        } catch (\Throwable $e) {
            $this->journal->write($backup->backup_path, ['phase' => 'restore_failed', 'restore_error' => $e->getMessage()]);
            $this->saveBackupResult($backup, ['status' => 'failed', 'restore_status' => 'failed', 'last_error' => $e->getMessage()]);
            throw $e;
        } finally {
            if ($maintenanceDown && $verified) {
                $this->healthChecks->artisanCall('up');
                $this->restartWorkers();
            }
        }
    }

    private function refreshRuntime(): void
    {
        $this->healthChecks->artisanCall('optimize:clear');
        if (app()->runningUnitTests()) {
            app(PublicMediaService::class)->prepare();
            $this->publicAssetsPublisher->republishInstalledModules();
            $this->healthChecks->artisanCall('cms:modules:cache');
        } else {
            $this->healthChecks->refreshPublicRuntime();
        }
    }

    private function restartWorkers(): void
    {
        $this->healthChecks->artisanCall('queue:restart', [], false);
    }

    private function saveBackupResult(CoreBackup $backup, array $result): void
    {
        // The DB snapshot predates the backup record: recreate it after DB restore.
        try {
            $attributes = array_intersect_key($backup->getAttributes(), array_flip($backup->getFillable()));
            CoreBackup::query()->updateOrCreate(['backup_key' => $backup->backup_key], array_merge($attributes, $result));
        } catch (\Throwable $e) {
            // Durable journal remains authoritative when the database is unavailable.
            $this->journal->write($backup->backup_path, ['database_log_error' => $e->getMessage(), 'backup_result' => $result]);
        }
    }
}
