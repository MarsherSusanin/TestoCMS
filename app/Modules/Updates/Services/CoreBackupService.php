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
    ) {
    }

    public function createBackup(string $fromVersion, string $toVersion, ?int $actorId = null): CoreBackup
    {
        $backupsRoot = $this->environment->storageRoot().DIRECTORY_SEPARATOR.'backups';
        File::ensureDirectoryExists($backupsRoot);

        $backupKey = 'bkp_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6));
        $backupPath = $backupsRoot.DIRECTORY_SEPARATOR.$backupKey;
        $codePath = $backupPath.DIRECTORY_SEPARATOR.'code';
        File::ensureDirectoryExists($codePath);

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'paths' => [],
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

        $manifestPath = $backupPath.DIRECTORY_SEPARATOR.'manifest.json';
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $dbDumpPath = $this->createDatabaseDump($backupPath);

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

        $this->restoreDatabaseDump(trim((string) ($backup->db_dump_path ?? '')));
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

        if ($connection === 'pgsql') {
            $cfg = config('database.connections.pgsql', []);
            $command = [
                'pg_dump',
                '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
                '-p', (string) ($cfg['port'] ?? '5432'),
                '-U', (string) ($cfg['username'] ?? ''),
                '-d', (string) ($cfg['database'] ?? ''),
                '-f', $dumpPath,
            ];
            $this->runProcess($command, [
                'PGPASSWORD' => (string) ($cfg['password'] ?? ''),
            ], 'Database backup (pg_dump) failed');

            return $dumpPath;
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
        if ($dbDumpPath === '' || ! is_file($dbDumpPath)) {
            return;
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

        if ($connection === 'pgsql') {
            $cfg = config('database.connections.pgsql', []);
            $command = [
                'psql',
                '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
                '-p', (string) ($cfg['port'] ?? '5432'),
                '-U', (string) ($cfg['username'] ?? ''),
                '-d', (string) ($cfg['database'] ?? ''),
                '-f', $dbDumpPath,
            ];
            $this->runProcess($command, [
                'PGPASSWORD' => (string) ($cfg['password'] ?? ''),
            ], 'Database restore (psql) failed');

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
     * @param array<int, string> $command
     * @param array<string, string> $env
     */
    private function runProcess(array $command, array $env, string $errorPrefix): void
    {
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
