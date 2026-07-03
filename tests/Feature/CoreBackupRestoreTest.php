<?php

namespace Tests\Feature;

use App\Modules\Updates\Services\CoreBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * The DB half of backup/restore was previously untested: every rollback test
 * ran with db_dump_path=null. This exercises the sqlite dump+restore path
 * end-to-end through the public API and the missing-dump failure mode.
 */
class CoreBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspace(): array
    {
        $workRoot = storage_path('framework/testing/backup-restore-'.uniqid('', true));
        File::ensureDirectoryExists($workRoot.'/base/app');
        file_put_contents($workRoot.'/base/app/version.txt', 'OLD');

        $dbFile = $workRoot.'/db.sqlite';
        file_put_contents($dbFile, 'ORIGINAL DB CONTENT');

        // The service reads these configs directly; the live test connection
        // (sqlite :memory:) is already established and stays untouched.
        config()->set('updates.base_path', $workRoot.'/base');
        config()->set('updates.storage_root', $workRoot.'/storage');
        config()->set('updates.allowlist_paths', ['app']);
        config()->set('updates.managed_public_paths', []);
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $dbFile);

        return [$workRoot, $dbFile];
    }

    public function test_sqlite_database_dump_is_created_and_restored_end_to_end(): void
    {
        [$workRoot, $dbFile] = $this->makeWorkspace();

        $backup = app(CoreBackupService::class)->createBackup('1.0.0', '1.1.0');

        $this->assertNotEmpty($backup->db_dump_path);
        $this->assertFileExists((string) $backup->db_dump_path);

        // Simulate a botched update: code and DB both corrupted.
        file_put_contents($dbFile, 'CORRUPTED');
        file_put_contents($workRoot.'/base/app/version.txt', 'BROKEN');

        app(CoreBackupService::class)->restoreSnapshot($backup);

        $this->assertSame('ORIGINAL DB CONTENT', file_get_contents($dbFile));
        $this->assertSame('OLD', file_get_contents($workRoot.'/base/app/version.txt'));
    }

    public function test_restore_fails_loudly_when_recorded_db_dump_is_missing(): void
    {
        [, $dbFile] = $this->makeWorkspace();

        $backup = app(CoreBackupService::class)->createBackup('1.0.0', '1.1.0');
        $this->assertFileExists((string) $backup->db_dump_path);
        unlink((string) $backup->db_dump_path);

        file_put_contents($dbFile, 'CORRUPTED');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database dump referenced by backup is missing');

        app(CoreBackupService::class)->restoreSnapshot($backup);
    }
}
