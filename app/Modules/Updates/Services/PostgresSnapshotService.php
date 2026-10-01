<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Full restore into a new dedicated database; the live database is never overlaid. */
class PostgresSnapshotService
{
    public function __construct(private readonly UpdateOperationJournal $journal) {}

    public function assertCanRestore(?string $ownershipDatabase = null): void
    {
        if (! config('updates.pgsql_dedicated_database')) {
            throw new RuntimeException('PostgreSQL automatic rollback requires CMS_UPDATE_PGSQL_DEDICATED_DATABASE=true.');
        }
        $pdo = $this->connect((string) config('updates.pgsql_maintenance_database', 'postgres'));
        $statement = $pdo->prepare('SELECT d.datname, pg_get_userbyid(d.datdba) AS owner, r.rolcreatedb, r.rolsuper, r.rolname FROM pg_database d CROSS JOIN pg_roles r WHERE d.datname = ? AND r.rolname = current_user');
        $statement->execute([$ownershipDatabase ?? $this->database()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (! $row || (! $row['rolsuper'] && (! $row['rolcreatedb'] || $row['owner'] !== $row['rolname']))) {
            throw new RuntimeException('PostgreSQL rollback requires CREATEDB and ownership of the dedicated CMS database.');
        }
        if ($this->database() === (string) config('updates.pgsql_maintenance_database', 'postgres')) {
            throw new RuntimeException('The maintenance database must differ from the CMS database.');
        }
        foreach (['pg_dump', 'pg_restore', 'psql'] as $binary) {
            $process = new Process([$binary, '--version']);
            $process->setTimeout(10);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Required PostgreSQL binary is unavailable: '.$binary);
            }
            preg_match('/(\d+)\./', $process->getOutput(), $version);
            $serverMajor = (int) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            if ((int) ($version[1] ?? 0) !== $serverMajor) {
                throw new RuntimeException('PostgreSQL dump/restore binaries must match the server major version.');
            }
        }
    }

    public function dump(string $directory): array
    {
        $this->assertCanRestore();
        $pdo = $this->connect($this->database());
        $databaseStatement = $pdo->prepare('SELECT pg_encoding_to_char(encoding) AS encoding, datcollate, datctype, datlocprovider, daticulocale, datcollversion, pg_get_userbyid(datdba) AS owner FROM pg_database WHERE datname = ?');
        $databaseStatement->execute([$this->database()]);
        $metadata = $databaseStatement->fetch(PDO::FETCH_ASSOC);
        if (! is_array($metadata)) {
            throw new RuntimeException('Cannot inspect PostgreSQL database metadata.');
        }
        $metadata['database'] = $this->database();
        $metadata['settings'] = $pdo->query('SELECT unnest(setconfig) AS setting FROM pg_db_role_setting WHERE setdatabase = (SELECT oid FROM pg_database WHERE datname = current_database()) AND setrole = 0')->fetchAll(PDO::FETCH_COLUMN);
        $metadata['tables'] = $this->tableDigests($pdo);
        $metadata['sequences'] = $this->sequences($pdo);
        $metadata['schema_sha256'] = $this->schemaDigest($directory, $this->database());
        $path = $directory.'/db_dump.pgdump';
        $this->run('pg_dump', ['--format=custom', '--file='.$path], $this->database());
        chmod($path, 0600);

        return ['path' => $path, 'metadata' => $metadata];
    }

    public function restore(string $dump, array $metadata, string $directory): void
    {
        $target = $this->database();
        if (isset($metadata['database']) && $metadata['database'] !== $target) {
            throw new RuntimeException('Backup belongs to a different PostgreSQL database.');
        }
        $statePath = $directory.'/operation.json';
        $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : [];
        $state = is_array($state) ? $state : [];
        $stage = $state['postgres_stage'] ?? substr($target, 0, 30).'_restore_'.bin2hex(random_bytes(6));
        $retained = $state['postgres_retained'] ?? substr($target, 0, 30).'_failed_'.bin2hex(random_bytes(6));
        $admin = $this->connect((string) config('updates.pgsql_maintenance_database', 'postgres'));
        $this->assertCanRestore($this->exists($admin, $target) ? $target : $retained);
        $this->journal->write($directory, ['postgres_target' => $target, 'postgres_stage' => $stage, 'postgres_retained' => $retained]);

        // Recover from a killed process during the two separate RENAME commands.
        if ($this->exists($admin, $retained)) {
            if ($this->exists($admin, $target)) {
                $this->allowConnections($admin, $target, true);
                $this->validate($directory, $target, $metadata);
                $this->allowConnections($admin, $target, true);
                $this->journal->write($directory, ['postgres_phase' => 'switched']);

                return;
            }
            if ($this->exists($admin, $stage)) {
                $this->rename($admin, $stage, $target);
                $this->allowConnections($admin, $target, true);
                $this->validate($directory, $target, $metadata);
                $this->journal->write($directory, ['postgres_phase' => 'switched']);

                return;
            }
            $this->rename($admin, $retained, $target);
            $this->allowConnections($admin, $target, true);
            throw new RuntimeException('Recovered original database after interrupted restore; retry the restore.');
        }

        if ($this->exists($admin, $stage)) {
            // A prior incomplete staging restore is disposable; the live DB is intact.
            $this->disconnectDatabase($admin, $stage);
            $admin->exec('DROP DATABASE '.$this->identifier($stage));
        }
        $this->journal->write($directory, ['postgres_phase' => 'preparing']);
        $this->createStage($admin, $stage, $metadata);
        $this->loadDump($dump, $stage);
        $this->applySettings($admin, $stage, $metadata['settings'] ?? []);
        $this->validate($directory, $stage, $metadata);
        $this->journal->write($directory, ['postgres_phase' => 'validated']);

        DB::disconnect((string) config('database.default'));
        $this->disconnectDatabase($admin, $stage);
        $this->disconnectDatabase($admin, $target);
        try {
            $this->journal->write($directory, ['postgres_phase' => 'renaming_original']);
            $this->rename($admin, $target, $retained);
            $this->journal->write($directory, ['postgres_phase' => 'original_retained']);
            $this->rename($admin, $stage, $target);
            $this->allowConnections($admin, $target, true);
            $this->journal->write($directory, ['postgres_phase' => 'switched']);
        } catch (\Throwable $e) {
            if (! $this->exists($admin, $target) && $this->exists($admin, $retained)) {
                $this->rename($admin, $retained, $target);
            }
            if ($this->exists($admin, $target)) {
                $this->allowConnections($admin, $target, true);
            }
            $this->journal->write($directory, ['postgres_error' => $e->getMessage()]);
            throw $e;
        }
        DB::purge((string) config('database.default'));
    }

    /** Rehearse the snapshot before applying any files/migrations. */
    public function verifyDump(string $dump, array $metadata, string $directory): void
    {
        $admin = $this->connect((string) config('updates.pgsql_maintenance_database', 'postgres'));
        $stage = substr($this->database(), 0, 30).'_verify_'.bin2hex(random_bytes(6));
        try {
            $this->createStage($admin, $stage, $metadata);
            $this->loadDump($dump, $stage);
            $this->applySettings($admin, $stage, $metadata['settings'] ?? []);
            $this->validate($directory, $stage, $metadata);
        } finally {
            if ($this->exists($admin, $stage)) {
                $this->disconnectDatabase($admin, $stage);
                $admin->exec('DROP DATABASE '.$this->identifier($stage));
            }
        }
    }

    private function loadDump(string $dump, string $stage): void
    {
        if (file_get_contents($dump, false, null, 0, 5) === 'PGDMP') {
            $this->run('pg_restore', ['--single-transaction', '--exit-on-error', $dump], $stage);
        } else {
            $this->run('psql', ['--no-psqlrc', '--single-transaction', '--set=ON_ERROR_STOP=1', '--file='.$dump], $stage);
        }
    }

    private function validate(string $directory, string $database, array $metadata): void
    {
        $pdo = $this->connect($database);
        if (isset($metadata['schema_sha256']) && ! hash_equals($metadata['schema_sha256'], $this->schemaDigest($directory, $database))) {
            throw new RuntimeException('Restored PostgreSQL schema does not match the snapshot.');
        }
        if (isset($metadata['tables']) && $metadata['tables'] !== $this->tableDigests($pdo)) {
            throw new RuntimeException('Restored PostgreSQL table data does not match the snapshot.');
        }
        if (isset($metadata['sequences']) && $metadata['sequences'] !== $this->sequences($pdo)) {
            throw new RuntimeException('Restored PostgreSQL sequences do not match the snapshot.');
        }
        $pdo->query('SELECT 1');
    }

    private function tableDigests(PDO $pdo): array
    {
        $tables = $pdo->query("SELECT n.nspname, c.relname FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE c.relkind IN ('r','p') AND n.nspname NOT IN ('pg_catalog','information_schema') AND n.nspname NOT LIKE 'pg_toast%' ORDER BY n.nspname,c.relname")->fetchAll(PDO::FETCH_ASSOC);
        $digests = [];
        foreach ($tables as $table) {
            $qualified = $this->identifier($table['nspname']).'.'.$this->identifier($table['relname']);
            $statement = $pdo->query('SELECT row_to_json(t)::text FROM '.$qualified.' t ORDER BY row_to_json(t)::text COLLATE "C"');
            $hash = hash_init('sha256');
            $count = 0;
            while (($row = $statement->fetchColumn()) !== false) {
                hash_update($hash, strlen($row).':'.$row."\n");
                $count++;
            }
            $digests[$table['nspname'].'.'.$table['relname']] = ['rows' => $count, 'sha256' => hash_final($hash)];
        }

        return $digests;
    }

    private function sequences(PDO $pdo): array
    {
        $rows = $pdo->query("SELECT n.nspname, c.relname FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE c.relkind='S' AND n.nspname NOT IN ('pg_catalog','information_schema') ORDER BY n.nspname,c.relname")->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['nspname'].'.'.$row['relname']] = $pdo->query('SELECT last_value,is_called FROM '.$this->identifier($row['nspname']).'.'.$this->identifier($row['relname']))->fetch(PDO::FETCH_ASSOC);
        }

        return $result;
    }

    private function schemaDigest(string $directory, string $database): string
    {
        $path = $directory.'/schema-validation-'.bin2hex(random_bytes(6)).'.sql';
        try {
            $this->run('pg_dump', ['--schema-only', '--no-owner', '--no-privileges', '--file='.$path], $database);
            $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
            $lines = array_filter($lines, static fn (string $line): bool => ! str_starts_with($line, '--') && ! preg_match('/^\\\\(?:un)?restrict\s/', $line));

            return hash('sha256', implode("\n", $lines));
        } finally {
            @unlink($path);
        }
    }

    private function createStage(PDO $admin, string $stage, array $metadata): void
    {
        $options = ' TEMPLATE template0';
        foreach (['encoding' => 'ENCODING', 'datcollate' => 'LC_COLLATE', 'datctype' => 'LC_CTYPE'] as $key => $option) {
            if (isset($metadata[$key])) {
                $options .= ' '.$option.' '.$admin->quote($metadata[$key]);
            }
        }
        if (($metadata['datlocprovider'] ?? 'c') === 'i') {
            $options .= ' LOCALE_PROVIDER icu ICU_LOCALE '.$admin->quote($metadata['daticulocale']);
        }
        if (isset($metadata['owner'])) {
            $options .= ' OWNER '.$this->identifier($metadata['owner']);
        }
        $admin->exec('CREATE DATABASE '.$this->identifier($stage).' WITH'.$options);
    }

    private function applySettings(PDO $admin, string $database, array $settings): void
    {
        foreach ($settings as $setting) {
            [$key, $value] = explode('=', $setting, 2);
            $admin->exec('ALTER DATABASE '.$this->identifier($database).' SET '.$this->identifier($key).' TO '.$admin->quote($value));
        }
    }

    private function disconnectDatabase(PDO $admin, string $database): void
    {
        $this->allowConnections($admin, $database, false);
        $statement = $admin->prepare('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()');
        $statement->execute([$database]);
        $statement->fetchAll();
    }

    private function allowConnections(PDO $admin, string $database, bool $allow): void
    {
        $admin->exec('ALTER DATABASE '.$this->identifier($database).' WITH ALLOW_CONNECTIONS '.($allow ? 'true' : 'false'));
    }

    private function exists(PDO $admin, string $database): bool
    {
        $statement = $admin->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $statement->execute([$database]);

        return $statement->fetchColumn() !== false;
    }

    private function rename(PDO $admin, string $from, string $to): void
    {
        $admin->exec('ALTER DATABASE '.$this->identifier($from).' RENAME TO '.$this->identifier($to));
    }

    private function identifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }

    private function database(): string
    {
        return (string) $this->configuration()['database'];
    }

    private function configuration(): array
    {
        return DB::connection((string) config('database.default'))->getConfig();
    }

    private function connect(string $database): PDO
    {
        $config = $this->configuration();
        $dsn = 'pgsql:host='.($config['host'] ?? '127.0.0.1').';port='.($config['port'] ?? 5432).';dbname='.$database.';sslmode='.($config['sslmode'] ?? 'prefer');

        return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function run(string $binary, array $arguments, string $database): void
    {
        $config = $this->configuration();
        $command = array_merge([$binary, '-h', (string) ($config['host'] ?? '127.0.0.1'), '-p', (string) ($config['port'] ?? 5432), '-U', (string) ($config['username'] ?? ''), '-d', $database], $arguments);
        $process = new Process($command, null, ['PGPASSWORD' => (string) ($config['password'] ?? ''), 'PGSSLMODE' => (string) ($config['sslmode'] ?? 'prefer')]);
        $process->setTimeout((int) config('updates.database_process_timeout', 1800));
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException($binary.' failed (exit '.$process->getExitCode().'): '.trim($process->getErrorOutput()));
        }
    }
}
