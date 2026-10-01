<?php

namespace App\Modules\Updates\Services;

use App\Models\CoreBackup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class CoreBackupService
{
    public function __construct(
        private readonly CoreUpdateSettingsService $settings,
        private readonly CoreUpdateEnvironment $environment,
        private readonly ManagedPublicRootSyncService $publicRootSync,
        private readonly PostgresSnapshotService $postgresSnapshots,
    ) {}

    public function createBackup(string $fromVersion, string $toVersion, ?int $actorId = null): CoreBackup
    {
        $backupsRoot = $this->environment->storageRoot().DIRECTORY_SEPARATOR.'backups';
        File::ensureDirectoryExists($backupsRoot);

        $backupKey = 'bkp_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6));
        $backupPath = $backupsRoot.DIRECTORY_SEPARATOR.$backupKey;
        $codePath = $backupPath.DIRECTORY_SEPARATOR.'code';
        File::ensureDirectoryExists($codePath);

        $manifest = [
            'format_version' => 2,
            'created_at' => now()->toIso8601String(),
            'paths' => [],
            'public_root' => $this->publicRootSync->activePublicRootPath(),
            'public_paths' => [],
        ];

        foreach ($this->environment->allowlistPaths() as $relative) {
            $source = $this->environment->rootPath().DIRECTORY_SEPARATOR.$relative;
            $snapshot = $codePath.DIRECTORY_SEPARATOR.$relative;
            $exists = file_exists($source) || is_link($source);
            $type = 'missing';

            if ($exists) {
                if (is_link($source)) {
                    $type = 'link';
                    $linkTarget = readlink($source);
                    if ($linkTarget === false) {
                        throw new RuntimeException('Failed to read symlink backup path: '.$relative);
                    }
                    File::ensureDirectoryExists(dirname($snapshot));
                    if (! symlink($linkTarget, $snapshot)) {
                        throw new RuntimeException('Failed to snapshot symlink: '.$relative);
                    }
                } elseif (is_dir($source)) {
                    $type = 'dir';
                    File::copyDirectory($source, $snapshot);
                } else {
                    $type = 'file';
                    File::ensureDirectoryExists(dirname($snapshot));
                    File::copy($source, $snapshot);
                }
            }

            $manifest['paths'][] = [
                'path' => $relative,
                'exists' => $exists,
                'type' => $type,
            ];
        }

        $publicSnapshotPath = $backupPath.DIRECTORY_SEPARATOR.'public';
        $manifest['public_paths'] = $this->publicRootSync->snapshotManagedPaths($publicSnapshotPath);

        if ((string) config('database.default') === 'pgsql') {
            $databaseSnapshot = $this->postgresSnapshots->dump($backupPath);
            $dbDumpPath = $databaseSnapshot['path'];
            $manifest['postgres'] = $databaseSnapshot['metadata'];
            $this->postgresSnapshots->verifyDump($dbDumpPath, $manifest['postgres'], $backupPath);
            $manifest['postgres']['verified_at'] = now()->toIso8601String();
        } else {
            $dbDumpPath = $this->createDatabaseDump($backupPath);
        }
        $manifest['checksums'] = $this->snapshotChecksums($backupPath);
        $manifestPath = $backupPath.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest['backup'] = [
            'backup_key' => $backupKey, 'from_version' => $fromVersion, 'to_version' => $toVersion,
            'backup_path' => $backupPath, 'db_dump_path' => $dbDumpPath, 'manifest_path' => $manifestPath,
            'actor_id' => $actorId,
        ];
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        chmod($manifestPath, 0600);

        $backup = CoreBackup::query()->create([
            'backup_key' => $backupKey,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'status' => 'created',
            'backup_path' => $backupPath,
            'db_dump_path' => $dbDumpPath,
            'manifest_path' => $manifestPath,
            'restore_status' => null,
            'actor_id' => $actorId,
        ]);

        $this->purgeOldBackups((int) ($this->settings->resolved()['backup_retention'] ?? 5));

        return $backup;
    }

    public function restoreSnapshot(CoreBackup $backup): void
    {
        $backupPath = trim((string) $backup->backup_path);
        $manifestPath = trim((string) $backup->manifest_path);
        if ($backupPath === '' || ! is_dir($backupPath)) {
            throw new RuntimeException('Backup directory does not exist: '.$backupPath);
        }
        if ($manifestPath === '' || ! is_file($manifestPath)) {
            throw new RuntimeException('Backup manifest does not exist: '.$manifestPath);
        }

        $rawManifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($rawManifest) || ! is_array($rawManifest['paths'] ?? null)) {
            throw new RuntimeException('Backup manifest format is invalid.');
        }

        $dumpPath = trim((string) ($backup->db_dump_path ?? ''));
        if ($dumpPath !== '' && ! is_file($dumpPath)) {
            throw new RuntimeException('Database dump referenced by backup is missing: '.$dumpPath);
        }
        foreach ($rawManifest['checksums'] ?? [] as $relative => $checksum) {
            if (! is_string($relative) || str_contains($relative, '..') || str_starts_with($relative, '/')) {
                throw new RuntimeException('Invalid backup checksum path.');
            }
            $path = $backupPath.'/'.$relative;
            $actual = is_link($path) ? hash('sha256', 'link:'.readlink($path)) : (is_file($path) ? hash_file('sha256', $path) : false);
            if ($actual === false || ! hash_equals($checksum, $actual)) {
                throw new RuntimeException('Backup checksum mismatch: '.$relative);
            }
        }
        foreach ($rawManifest['paths'] as $entry) {
            if (! empty($entry['exists'])) {
                $snapshot = $backupPath.'/code/'.($entry['path'] ?? '');
                if (! file_exists($snapshot) && ! is_link($snapshot)) {
                    throw new RuntimeException('Snapshot path missing for rollback: '.($entry['path'] ?? ''));
                }
            }
        }
        if ((string) config('database.default') === 'pgsql') {
            if ($dumpPath === '') {
                throw new RuntimeException('PostgreSQL automatic restore requires a database snapshot.');
            }
            $this->postgresSnapshots->restore($dumpPath, $rawManifest['postgres'] ?? [], $backupPath);
        }

        foreach ($rawManifest['paths'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $relative = trim((string) ($entry['path'] ?? ''));
            if ($relative === '' || ! in_array($relative, $this->environment->allowlistPaths(), true)) {
                continue;
            }

            $target = $this->environment->rootPath().DIRECTORY_SEPARATOR.$relative;
            $snapshot = $backupPath.DIRECTORY_SEPARATOR.'code'.DIRECTORY_SEPARATOR.$relative;

            $this->deletePath($target);

            if (! empty($entry['exists'])) {
                if (! (file_exists($snapshot) || is_link($snapshot))) {
                    throw new RuntimeException('Snapshot path missing for rollback: '.$relative);
                }
                $this->copyPath($snapshot, $target);
            }
        }

        if (is_array($rawManifest['public_paths'] ?? null)) {
            $this->publicRootSync->restoreManagedPaths(
                $backupPath.DIRECTORY_SEPARATOR.'public',
                $rawManifest['public_paths']
            );
        }

        if ((string) config('database.default') !== 'pgsql') {
            $this->restoreDatabaseDump($dumpPath);
        }
    }

    private function createDatabaseDump(string $backupPath): ?string
    {
        $connection = (string) config('database.default', 'pgsql');
        $dumpPath = $backupPath.DIRECTORY_SEPARATOR.'db_dump.sql';

        if ($connection === 'sqlite') {
            $dbFile = (string) config('database.connections.sqlite.database', '');
            if ($dbFile === '' || $dbFile === ':memory:') {
                return null;
            }
            if (! is_file($dbFile)) {
                throw new RuntimeException('SQLite database file is missing: '.$dbFile);
            }
            File::copy($dbFile, $dumpPath);

            return $dumpPath;
        }

        if (($connection === 'pgsql' || $connection === 'mysql') && ! $this->canRunProcess()) {
            // Shared hosting commonly disables proc_open / omits dump binaries.
            // Degrade to a file-only backup rather than failing the whole update,
            // and surface a warning so the operator takes a manual DB backup.
            report(new RuntimeException(sprintf(
                'Skipping %s database dump for core backup: proc_open is unavailable. The update will proceed without an automatic DB backup — take a manual database backup first.',
                $connection
            )));

            return null;
        }

        if ($connection === 'mysql') {
            $cfg = config('database.connections.mysql', []);
            $command = [
                'mysqldump',
                '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
                '-P', (string) ($cfg['port'] ?? '3306'),
                '-u', (string) ($cfg['username'] ?? ''),
                '--result-file='.$dumpPath,
                (string) ($cfg['database'] ?? ''),
            ];
            $this->runProcess($command, [
                'MYSQL_PWD' => (string) ($cfg['password'] ?? ''),
            ], 'Database backup (mysqldump) failed');

            return $dumpPath;
        }

        throw new RuntimeException('Unsupported DB connection for backup: '.$connection);
    }

    private function restoreDatabaseDump(string $dbDumpPath): void
    {
        // Empty = the backup was file-only by design (no dump was taken).
        if ($dbDumpPath === '') {
            return;
        }

        // A recorded dump that vanished must fail the restore loudly: silently
        // restoring code without the DB it was backed up with is a data trap.
        if (! is_file($dbDumpPath)) {
            throw new RuntimeException('Database dump referenced by backup is missing: '.$dbDumpPath);
        }

        $connection = (string) config('database.default', 'pgsql');

        if ($connection === 'sqlite') {
            $dbFile = (string) config('database.connections.sqlite.database', '');
            if ($dbFile === '' || $dbFile === ':memory:') {
                return;
            }
            File::copy($dbDumpPath, $dbFile);

            return;
        }

        if ($connection === 'mysql') {
            $cfg = config('database.connections.mysql', []);
            $command = [
                'mysql',
                '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
                '-P', (string) ($cfg['port'] ?? '3306'),
                '-u', (string) ($cfg['username'] ?? ''),
                (string) ($cfg['database'] ?? ''),
                '--execute', 'source '.$dbDumpPath,
            ];
            $this->runProcess($command, [
                'MYSQL_PWD' => (string) ($cfg['password'] ?? ''),
            ], 'Database restore (mysql) failed');

            return;
        }

        throw new RuntimeException('Unsupported DB connection for restore: '.$connection);
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     */
    private function canRunProcess(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('proc_open', $disabled, true);
    }

    private function runProcess(array $command, array $env, string $errorPrefix): void
    {
        if (! $this->canRunProcess()) {
            throw new RuntimeException($errorPrefix.': proc_open is disabled in this PHP environment.');
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $this->environment->rootPath(), array_merge($_ENV, $env));
        if (! is_resource($process)) {
            throw new RuntimeException($errorPrefix.': process init failed.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf('%s: %s %s', $errorPrefix, trim((string) $stdout), trim((string) $stderr)));
        }
    }

    private function purgeOldBackups(int $retention): void
    {
        $retention = max(1, min(50, $retention));

        $toDelete = CoreBackup::query()
            ->orderByDesc('id')
            ->skip($retention)
            ->take(1000)
            ->get();

        foreach ($toDelete as $backup) {
            $path = trim((string) $backup->backup_path);
            if ($path !== '' && is_dir($path)) {
                File::deleteDirectory($path);
            }
            $backup->delete();
        }
    }

    private function snapshotChecksums(string $directory): array
    {
        $checksums = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $relative = substr($path, strlen($directory) + 1);
            if ($file->isLink()) {
                $checksums[$relative] = hash('sha256', 'link:'.readlink($path));
            } elseif ($file->isFile()) {
                $checksums[$relative] = hash_file('sha256', $path);
            }
        }
        ksort($checksums);

        return $checksums;
    }

    private function deletePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (is_dir($path)) {
            File::deleteDirectory($path);
        }
    }

    private function copyPath(string $source, string $target): void
    {
        if (is_link($source)) {
            $linkTarget = readlink($source);
            if ($linkTarget === false) {
                throw new RuntimeException('Failed to read symlink source: '.$source);
            }
            File::ensureDirectoryExists(dirname($target));
            if (! @symlink($linkTarget, $target)) {
                throw new RuntimeException('Failed to copy symlink to target: '.$target);
            }

            return;
        }

        if (is_dir($source)) {
            File::copyDirectory($source, $target);

            return;
        }

        if (is_file($source)) {
            File::ensureDirectoryExists(dirname($target));
            File::copy($source, $target);

            return;
        }

        throw new RuntimeException('Unable to copy unknown path type: '.$source);
    }
}
