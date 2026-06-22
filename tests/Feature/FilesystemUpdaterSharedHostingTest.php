<?php

namespace Tests\Feature;

use App\Models\CmsModule;
use App\Models\CoreBackup;
use App\Modules\Updates\Services\CorePackageApplier;
use App\Modules\Updates\Services\FilesystemUpdateDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class FilesystemUpdaterSharedHostingTest extends TestCase
{
    use RefreshDatabase;

    private string $originalPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPublicPath = public_path();
    }

    protected function tearDown(): void
    {
        app()->usePublicPath($this->originalPublicPath);
        config()->set('filesystems.links', [
            $this->originalPublicPath.'/storage' => storage_path('app/public'),
        ]);

        parent::tearDown();
    }

    public function test_filesystem_updater_syncs_managed_public_root_and_republishes_module_assets(): void
    {
        [$baseRoot, $publicRoot, $updateStorageRoot] = $this->makeWorkspaceRoots();
        $this->seedBaseInstall($baseRoot, 'OLD');
        $this->seedSharedHostingPublicRoot($publicRoot, 'OLD');
        $this->installModuleFixture('acme/demo');
        $zipPath = $this->makeUpdaterZip('1.2.0', 'NEW');

        $result = $this->applyUpdate($baseRoot, $publicRoot, $updateStorageRoot, $zipPath);

        $this->assertSame('NEW', trim((string) file_get_contents($baseRoot.'/app/version.txt')));
        $this->assertSame('NEW INDEX', trim((string) file_get_contents($publicRoot.'/index.php')));
        $this->assertSame('NEW BRAND', trim((string) file_get_contents($publicRoot.'/brand/logo.txt')));
        $this->assertSame('KEEP', trim((string) file_get_contents($publicRoot.'/.well-known/acme-challenge.txt')));
        $this->assertSame('ORPHAN', trim((string) file_get_contents($publicRoot.'/modules/orphan.txt')));
        $this->assertTrue(is_link($publicRoot.'/storage'));
        $this->assertSame('module asset', trim((string) file_get_contents($publicRoot.'/modules/acme--demo/widget.js')));
        $this->assertSame('success', $result['status']);
    }

    public function test_filesystem_updater_rollback_restores_managed_public_root_and_storage_link(): void
    {
        [$baseRoot, $publicRoot, $updateStorageRoot] = $this->makeWorkspaceRoots();
        $this->seedBaseInstall($baseRoot, 'OLD');
        $this->seedSharedHostingPublicRoot($publicRoot, 'OLD');
        $this->installModuleFixture('acme/demo');
        $zipPath = $this->makeUpdaterZip('1.2.0', 'NEW');

        $result = $this->applyUpdate($baseRoot, $publicRoot, $updateStorageRoot, $zipPath);

        file_put_contents($baseRoot.'/app/version.txt', 'BROKEN');
        file_put_contents($publicRoot.'/index.php', 'BROKEN INDEX');
        File::deleteDirectory($publicRoot.'/brand');
        @unlink($publicRoot.'/storage');
        File::deleteDirectory($publicRoot.'/modules/acme--demo');

        $backup = CoreBackup::query()->where('backup_key', $result['backup_key'])->firstOrFail();
        $rollback = app(FilesystemUpdateDriver::class)->rollback($backup);

        $this->assertSame('OLD', trim((string) file_get_contents($baseRoot.'/app/version.txt')));
        $this->assertSame('OLD INDEX', trim((string) file_get_contents($publicRoot.'/index.php')));
        $this->assertSame('OLD BRAND', trim((string) file_get_contents($publicRoot.'/brand/logo.txt')));
        $this->assertSame('KEEP', trim((string) file_get_contents($publicRoot.'/.well-known/acme-challenge.txt')));
        $this->assertSame('module asset', trim((string) file_get_contents($publicRoot.'/modules/acme--demo/widget.js')));
        $this->assertTrue(is_link($publicRoot.'/storage'));
        $this->assertSame('success', $rollback['status']);

        $backup->refresh();
        $this->assertSame('rolled_back', $backup->status);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function makeWorkspaceRoots(): array
    {
        $suffix = uniqid('', true);
        $baseRoot = storage_path('framework/testing/core-updater-base-'.$suffix);
        $publicRoot = storage_path('framework/testing/core-updater-public-'.$suffix);
        $updateStorageRoot = storage_path('framework/testing/core-updater-storage-'.$suffix);

        File::ensureDirectoryExists($baseRoot);
        File::ensureDirectoryExists($publicRoot);
        File::ensureDirectoryExists($updateStorageRoot);

        return [$baseRoot, $publicRoot, $updateStorageRoot];
    }

    private function seedBaseInstall(string $baseRoot, string $marker): void
    {
        foreach ([
            'app',
            'bootstrap',
            'bundled-modules',
            'config',
            'database',
            'html_public/brand',
            'lang',
            'resources',
            'routes',
            'vendor',
        ] as $relative) {
            File::ensureDirectoryExists($baseRoot.'/'.$relative);
        }

        file_put_contents($baseRoot.'/app/version.txt', $marker);
        file_put_contents($baseRoot.'/html_public/index.php', $marker.' SOURCE');
        file_put_contents($baseRoot.'/html_public/brand/logo.txt', $marker.' SOURCE BRAND');
        file_put_contents($baseRoot.'/bootstrap/app.php', '<?php return [];');
        file_put_contents($baseRoot.'/composer.json', '{"name":"testocms/testocms"}');
        file_put_contents($baseRoot.'/composer.lock', '{"packages":[]}');
        file_put_contents($baseRoot.'/artisan', "#!/usr/bin/env php\n<?php echo 'artisan';\n");
        file_put_contents($baseRoot.'/vendor/autoload.php', '<?php');
    }

    private function seedSharedHostingPublicRoot(string $publicRoot, string $marker): void
    {
        File::ensureDirectoryExists($publicRoot.'/brand');
        File::ensureDirectoryExists($publicRoot.'/.well-known');
        File::ensureDirectoryExists($publicRoot.'/modules');

        file_put_contents($publicRoot.'/index.php', $marker.' INDEX');
        file_put_contents($publicRoot.'/brand/logo.txt', $marker.' BRAND');
        file_put_contents($publicRoot.'/.well-known/acme-challenge.txt', 'KEEP');
        file_put_contents($publicRoot.'/modules/orphan.txt', 'ORPHAN');
    }

    private function installModuleFixture(string $moduleKey): void
    {
        $installPath = storage_path('framework/testing/module-install-'.uniqid('', true));
        File::ensureDirectoryExists($installPath.'/public');
        file_put_contents($installPath.'/public/widget.js', 'module asset');

        CmsModule::query()->create([
            'module_key' => $moduleKey,
            'name' => 'Demo module',
            'version' => '1.0.0',
            'description' => 'Test module',
            'author' => 'Tests',
            'install_path' => $installPath,
            'provider' => 'Tests\\DemoModuleServiceProvider',
            'checksum' => null,
            'enabled' => false,
            'status' => 'installed',
            'installed_at' => now(),
            'updated_at_module' => now(),
            'metadata' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function applyUpdate(string $baseRoot, string $publicRoot, string $updateStorageRoot, string $zipPath): array
    {
        app()->usePublicPath($publicRoot);
        config()->set('filesystems.links', [
            $publicRoot.'/storage' => storage_path('app/public'),
        ]);
        config()->set('modules.cache_file', $updateStorageRoot.'/cms_modules.php');
        config()->set('updates.base_path', $baseRoot);
        config()->set('updates.storage_root', $updateStorageRoot);
        config()->set('updates.current_version', '1.0.0');
        // Preflight requires a signing key to be configured for signed sources.
        config()->set('updates.public_key', base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())));

        $inspection = app(CorePackageApplier::class)->inspectArchive($zipPath);

        return app(FilesystemUpdateDriver::class)->apply([
            'source' => 'manual',
            'version' => '1.2.0',
            'zip_path' => $zipPath,
            'release' => $inspection['release'],
        ]);
    }

    private function makeUpdaterZip(string $version, string $marker): string
    {
        $tmpDir = storage_path('framework/testing/core-updater-package-'.uniqid('', true));

        foreach ([
            'app',
            'bootstrap',
            'bundled-modules',
            'config',
            'database',
            'html_public/brand',
            'lang',
            'resources',
            'routes',
            'vendor',
        ] as $relative) {
            File::ensureDirectoryExists($tmpDir.'/'.$relative);
        }

        file_put_contents($tmpDir.'/release.json', json_encode([
            'artifact' => 'core-updater',
            'version' => $version,
            'build' => 'test',
            'signed_at' => now()->toIso8601String(),
            'compat' => [
                'php' => '>=8.2',
                'cms_from' => '>=1.0.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($tmpDir.'/app/version.txt', $marker);
        file_put_contents($tmpDir.'/bootstrap/app.php', '<?php return [];');
        file_put_contents($tmpDir.'/config/app.php', '<?php return [];');
        file_put_contents($tmpDir.'/composer.json', '{"name":"testocms/testocms"}');
        file_put_contents($tmpDir.'/composer.lock', '{"packages":[]}');
        file_put_contents($tmpDir.'/artisan', "#!/usr/bin/env php\n<?php echo 'artisan';\n");
        file_put_contents($tmpDir.'/vendor/autoload.php', '<?php');
        file_put_contents($tmpDir.'/html_public/index.php', $marker.' INDEX');
        file_put_contents($tmpDir.'/html_public/brand/logo.txt', $marker.' BRAND');

        $zipPath = storage_path('framework/testing/core-updater-package-'.uniqid('', true).'.zip');
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Failed to create updater ZIP');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $pathname = (string) $item->getPathname();
            $relative = ltrim(str_replace($tmpDir, '', $pathname), DIRECTORY_SEPARATOR);
            if ($item->isDir()) {
                $zip->addEmptyDir(str_replace('\\', '/', $relative));
            } else {
                $zip->addFile($pathname, str_replace('\\', '/', $relative));
            }
        }

        $zip->close();

        return $zipPath;
    }
}
