<?php

namespace Tests\Unit;

use App\Modules\Updates\Services\ManagedPublicRootSyncService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ManagedPublicRootSyncServiceTest extends TestCase
{
    private string $originalPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPublicPath = public_path();
    }

    protected function tearDown(): void
    {
        app()->usePublicPath($this->originalPublicPath);

        parent::tearDown();
    }

    public function test_sync_from_release_root_copies_only_managed_subset_and_preserves_unknown_paths(): void
    {
        $baseRoot = storage_path('framework/testing/public-sync-base-'.uniqid('', true));
        $publicRoot = storage_path('framework/testing/public-sync-target-'.uniqid('', true));

        File::ensureDirectoryExists($baseRoot.'/html_public/brand');
        File::ensureDirectoryExists($publicRoot.'/brand');
        File::ensureDirectoryExists($publicRoot.'/.well-known');
        File::ensureDirectoryExists($publicRoot.'/modules');

        file_put_contents($baseRoot.'/html_public/index.php', 'NEW INDEX');
        file_put_contents($baseRoot.'/html_public/bootstrap_path.php', 'NEW BOOTSTRAP');
        file_put_contents($baseRoot.'/html_public/brand/logo.txt', 'NEW BRAND');

        file_put_contents($publicRoot.'/index.php', 'OLD INDEX');
        file_put_contents($publicRoot.'/brand/logo.txt', 'OLD BRAND');
        file_put_contents($publicRoot.'/.well-known/acme.txt', 'KEEP');
        file_put_contents($publicRoot.'/modules/orphan.txt', 'PRESERVE');

        app()->usePublicPath($publicRoot);

        app(ManagedPublicRootSyncService::class)->syncFromReleaseRoot($baseRoot);

        $this->assertSame('NEW INDEX', trim((string) file_get_contents($publicRoot.'/index.php')));
        $this->assertSame('NEW BOOTSTRAP', trim((string) file_get_contents($publicRoot.'/bootstrap_path.php')));
        $this->assertSame('NEW BRAND', trim((string) file_get_contents($publicRoot.'/brand/logo.txt')));
        $this->assertSame('KEEP', trim((string) file_get_contents($publicRoot.'/.well-known/acme.txt')));
        $this->assertSame('PRESERVE', trim((string) file_get_contents($publicRoot.'/modules/orphan.txt')));
    }

    public function test_snapshot_and_restore_managed_paths_restores_deleted_files(): void
    {
        $publicRoot = storage_path('framework/testing/public-sync-restore-'.uniqid('', true));
        $snapshotRoot = storage_path('framework/testing/public-sync-snapshot-'.uniqid('', true));

        File::ensureDirectoryExists($publicRoot.'/brand');
        file_put_contents($publicRoot.'/index.php', 'ORIGINAL INDEX');
        file_put_contents($publicRoot.'/brand/logo.txt', 'ORIGINAL BRAND');

        app()->usePublicPath($publicRoot);

        $service = app(ManagedPublicRootSyncService::class);
        $entries = $service->snapshotManagedPaths($snapshotRoot);

        file_put_contents($publicRoot.'/index.php', 'BROKEN INDEX');
        File::deleteDirectory($publicRoot.'/brand');

        $service->restoreManagedPaths($snapshotRoot, $entries);

        $this->assertSame('ORIGINAL INDEX', trim((string) file_get_contents($publicRoot.'/index.php')));
        $this->assertSame('ORIGINAL BRAND', trim((string) file_get_contents($publicRoot.'/brand/logo.txt')));
    }
}
