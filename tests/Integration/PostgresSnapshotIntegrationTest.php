<?php

namespace Tests\Integration;

use App\Modules\Updates\Services\CoreBackupService;
use App\Modules\Updates\Services\PostgresSnapshotService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Explicit opt-in; NEVER point these tests at a normal CMS database. */
class PostgresSnapshotIntegrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $database = (string) getenv('CMS_PGSQL_TEST_DATABASE');
        if (getenv('CMS_RUN_DESTRUCTIVE_PG_TESTS') !== '1' || ! preg_match('/^testocms_p1(?:_|$)/', $database)) {
            $this->markTestSkipped('Requires a disposable testocms_p1_* database and CMS_RUN_DESTRUCTIVE_PG_TESTS=1.');
        }
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => getenv('CMS_PGSQL_TEST_HOST') ?: '127.0.0.1',
            'database.connections.pgsql.port' => getenv('CMS_PGSQL_TEST_PORT') ?: 5432,
            'database.connections.pgsql.database' => $database,
            'database.connections.pgsql.username' => getenv('CMS_PGSQL_TEST_USERNAME') ?: 'postgres',
            'database.connections.pgsql.password' => getenv('CMS_PGSQL_TEST_PASSWORD') ?: '',
            'database.connections.pgsql.sslmode' => 'disable',
            'updates.pgsql_dedicated_database' => true,
        ]);
        DB::purge('pgsql');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->root = storage_path('framework/testing/pg-restore-'.uniqid());
        File::ensureDirectoryExists($this->root.'/base/app');
        file_put_contents($this->root.'/base/app/version.txt', 'OLD');
        config(['updates.base_path' => $this->root.'/base', 'updates.storage_root' => $this->root.'/backups', 'updates.allowlist_paths' => ['app'], 'updates.managed_public_paths' => []]);
    }

    public function test_full_snapshot_and_interrupted_database_rename_are_restored(): void
    {
        DB::statement('CREATE TABLE snapshot_probe (id serial PRIMARY KEY, title text NOT NULL)');
        DB::statement('CREATE TABLE snapshot_deleted (id integer PRIMARY KEY)');
        DB::table('snapshot_probe')->insert(['title' => 'before']);
        DB::table('snapshot_deleted')->insert(['id' => 17]);
        $backup = app(CoreBackupService::class)->createBackup('1.0.0', '1.1.0');
        DB::table('snapshot_probe')->update(['title' => 'after']);
        DB::statement('ALTER TABLE snapshot_probe ADD COLUMN extra text');
        DB::statement('CREATE TABLE snapshot_added (id int)');
        DB::statement('DROP TABLE snapshot_deleted');
        DB::statement('ALTER SEQUENCE snapshot_probe_id_seq RESTART WITH 999');
        file_put_contents($this->root.'/base/app/version.txt', 'BROKEN');
        app(CoreBackupService::class)->restoreSnapshot($backup);
        $this->assertSame('before', DB::table('snapshot_probe')->value('title'));
        $this->assertFalse(Schema::hasColumn('snapshot_probe', 'extra'));
        $this->assertFalse(Schema::hasTable('snapshot_added'));
        $this->assertSame(17, DB::table('snapshot_deleted')->value('id'));
        $this->assertSame('OLD', file_get_contents($this->root.'/base/app/version.txt'));
        $this->assertSame(2, DB::table('snapshot_probe')->insertGetId(['title' => 'sequence']));

        $second = app(CoreBackupService::class)->createBackup('1.0.0', '1.1.0');
        DB::table('snapshot_probe')->where('id', 1)->update(['title' => 'changed']);
        app(CoreBackupService::class)->restoreSnapshot($second);
        $journal = json_decode(file_get_contents($second->backup_path.'/operation.json'), true);
        $this->assertSame('switched', $journal['postgres_phase']);
        $this->assertNotEmpty($journal['postgres_retained']);
        $configuration = DB::connection()->getConfig();
        DB::disconnect('pgsql');
        $admin = new PDO('pgsql:host='.$configuration['host'].';port='.$configuration['port'].';dbname=postgres;sslmode=disable', $configuration['username'], $configuration['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $target = '"'.str_replace('"', '""', $configuration['database']).'"';
        $admin->exec('ALTER DATABASE '.$target.' WITH ALLOW_CONNECTIONS false');
        $statement = $admin->prepare('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=?');
        $statement->execute([$configuration['database']]);
        $statement->fetchAll();
        $admin->exec('ALTER DATABASE '.$target.' RENAME TO "'.$journal['postgres_stage'].'"');
        app(CoreBackupService::class)->restoreSnapshot($second);
        DB::purge('pgsql');
        $this->assertSame('before', DB::table('snapshot_probe')->where('id', 1)->value('title'));
        $this->assertSame('switched', json_decode(file_get_contents($second->backup_path.'/operation.json'), true)['postgres_phase']);
    }

    public function test_corrupt_legacy_sql_and_non_dedicated_database_cannot_go_live(): void
    {
        DB::statement('CREATE TABLE snapshot_probe (title text)');
        DB::table('snapshot_probe')->insert(['title' => 'unchanged']);
        File::ensureDirectoryExists($this->root.'/bad');
        $dump = $this->root.'/bad.sql';
        file_put_contents($dump, "CREATE TABLE should_not_go_live (id int);\nTHIS IS NOT SQL;\n");
        try {
            app(PostgresSnapshotService::class)->restore($dump, [], $this->root.'/bad');
            $this->fail('Invalid SQL was accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('psql failed', $e->getMessage());
        }
        $this->assertFalse(Schema::hasTable('should_not_go_live'));
        $this->assertSame('unchanged', DB::table('snapshot_probe')->value('title'));
        config(['updates.pgsql_dedicated_database' => false]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEDICATED_DATABASE=true');
        app(PostgresSnapshotService::class)->assertCanRestore();
    }

    public static function insufficientPrivileges(): array
    {
        return [
            'CREATEDB without ownership' => [true, false],
            'ownership without CREATEDB' => [false, true],
        ];
    }

    #[DataProvider('insufficientPrivileges')]
    public function test_insufficient_role_privileges_are_rejected_before_database_backup(bool $createdb, bool $owner): void
    {
        $configuration = DB::connection('pgsql')->getConfig();
        $database = $configuration['database'];
        $role = 'testocms_p1_denied_'.bin2hex(random_bytes(6));
        $password = bin2hex(random_bytes(18));
        $admin = $this->localPdo($configuration, 'postgres');
        $live = $this->localPdo($configuration, $database);
        $live->exec("CREATE TABLE snapshot_permission_probe (title text NOT NULL); INSERT INTO snapshot_permission_probe VALUES ('unchanged')");
        $backupCount = (int) $live->query('SELECT count(*) FROM cms_core_backups')->fetchColumn();
        $admin->exec('CREATE ROLE '.$this->identifier($role).' LOGIN NOSUPERUSER NOCREATEROLE NOINHERIT '.($createdb ? 'CREATEDB' : 'NOCREATEDB').' PASSWORD '.$admin->quote($password));

        try {
            if ($owner) {
                $admin->exec('ALTER DATABASE '.$this->identifier($database).' OWNER TO '.$this->identifier($role));
            }
            $restrictedConfiguration = array_replace($configuration, ['username' => $role, 'password' => $password]);
            $restricted = $this->localPdo($restrictedConfiguration, 'postgres');
            $this->assertSame($role, $restricted->query('SELECT current_user')->fetchColumn());
            $this->assertFalse($restricted->query('SELECT rolsuper FROM pg_roles WHERE rolname=current_user')->fetchColumn());
            $this->assertSame($createdb, $restricted->query('SELECT rolcreatedb FROM pg_roles WHERE rolname=current_user')->fetchColumn());
            $restricted = null;
            config(['database.connections.pgsql' => $restrictedConfiguration]);
            DB::purge('pgsql');

            try {
                app(CoreBackupService::class)->createBackup('1.0.0', '1.1.0');
                $this->fail('A role without both CREATEDB and database ownership was allowed to create a restore-capable backup.');
            } catch (RuntimeException $e) {
                $this->assertSame('PostgreSQL rollback requires CREATEDB and ownership of the dedicated CMS database.', $e->getMessage());
            }
            $this->assertSame('unchanged', $live->query('SELECT title FROM snapshot_permission_probe')->fetchColumn());
            $this->assertSame($backupCount, (int) $live->query('SELECT count(*) FROM cms_core_backups')->fetchColumn());
            $this->assertSame('OLD', file_get_contents($this->root.'/base/app/version.txt'));
            $this->assertSame([], File::glob($this->root.'/backups/backups/*/db_dump.pgdump'));
        } finally {
            DB::purge('pgsql');
            config(['database.connections.pgsql' => $configuration]);
            if ($owner) {
                $admin->exec('ALTER DATABASE '.$this->identifier($database).' OWNER TO '.$this->identifier($configuration['username']));
            }
            $admin->exec('DROP ROLE '.$this->identifier($role));
        }
        $roleQuery = $admin->prepare('SELECT 1 FROM pg_roles WHERE rolname = ?');
        $roleQuery->execute([$role]);
        $this->assertFalse($roleQuery->fetchColumn());
    }

    public function test_restore_terminates_an_active_live_connection_and_switches_to_the_snapshot_database(): void
    {
        DB::statement('CREATE TABLE snapshot_live_probe (title text NOT NULL)');
        DB::table('snapshot_live_probe')->insert(['title' => 'before']);
        $configuration = DB::connection('pgsql')->getConfig();
        $external = $this->localPdo($configuration, $configuration['database']);
        $oldPid = (int) $external->query('SELECT pg_backend_pid()')->fetchColumn();
        $oldOid = (int) $external->query('SELECT oid FROM pg_database WHERE datname = current_database()')->fetchColumn();
        $backup = app(CoreBackupService::class)->createBackup('1.0.0', '1.1.0');
        DB::table('snapshot_live_probe')->update(['title' => 'after']);
        DB::statement('CREATE TABLE snapshot_live_added (id integer)');
        $this->assertSame('after', $external->query('SELECT title FROM snapshot_live_probe')->fetchColumn());
        $this->assertSame($oldPid, (int) $external->query('SELECT pg_backend_pid()')->fetchColumn());

        app(CoreBackupService::class)->restoreSnapshot($backup);

        try {
            $external->query('SELECT 1');
            $this->fail('The connection to the renamed live database remained usable.');
        } catch (PDOException $e) {
            $this->assertMatchesRegularExpression('/terminating connection|server closed the connection|connection (?:has been lost|not open)/i', $e->getMessage());
        }
        $external = null;
        $restored = $this->localPdo($configuration, $configuration['database']);
        $this->assertSame('before', $restored->query('SELECT title FROM snapshot_live_probe')->fetchColumn());
        $this->assertNotSame($oldOid, (int) $restored->query('SELECT oid FROM pg_database WHERE datname = current_database()')->fetchColumn());
        $this->assertNotSame($oldPid, (int) $restored->query('SELECT pg_backend_pid()')->fetchColumn());
        $this->assertFalse(Schema::hasTable('snapshot_live_added'));
        $journal = json_decode(file_get_contents($backup->backup_path.'/operation.json'), true);
        $this->assertSame('switched', $journal['postgres_phase']);
        $admin = $this->localPdo($configuration, 'postgres');
        $activity = $admin->prepare('SELECT 1 FROM pg_stat_activity WHERE pid = ?');
        $activity->execute([$oldPid]);
        $this->assertFalse($activity->fetchColumn());
        $retained = $admin->prepare('SELECT oid, datallowconn FROM pg_database WHERE datname = ?');
        $retained->execute([$journal['postgres_retained']]);
        $originalDatabase = $retained->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($oldOid, (int) $originalDatabase['oid']);
        $this->assertFalse($originalDatabase['datallowconn']);
    }

    private function localPdo(array $configuration, string $database): PDO
    {
        return new PDO('pgsql:host='.$configuration['host'].';port='.$configuration['port'].';dbname='.$database.';sslmode=disable', $configuration['username'], $configuration['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function identifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
