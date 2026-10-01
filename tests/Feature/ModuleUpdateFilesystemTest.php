<?php

namespace Tests\Feature;

use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Extensibility\Services\ModuleCacheService;
use App\Modules\Extensibility\Services\ModuleInstallerService;
use App\Modules\Extensibility\Services\ModuleManifestParserService;
use App\Modules\Extensibility\Services\ModulePublicAssetsPublisherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ModuleUpdateFilesystemTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/cms-module-update-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory.'/public');
        $this->public = public_path();
        $this->app->usePublicPath($this->directory.'/public');
        config(['modules.modules_root' => $this->directory.'/modules', 'modules.upload_tmp_root' => $this->directory.'/uploads', 'modules.cache_file' => $this->directory.'/cache/modules.php']);
    }

    protected function tearDown(): void
    {
        $this->app->usePublicPath($this->public);
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function zip(string $version): UploadedFile
    {
        $path = $this->directory.'/fixture-'.$version.'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('module.json', json_encode(['id' => 'audit/update-fixture', 'name' => 'Fixture', 'version' => $version, 'provider' => 'Audit\\Fixture\\Provider', 'autoload' => ['psr-4' => ['Audit\\Fixture\\' => 'src/']], 'requires' => ['cms' => '>=1.0.0', 'php' => '>=8.2']], JSON_THROW_ON_ERROR));
        $zip->addFromString('src/Provider.php', '<?php namespace Audit\\Fixture; class Provider extends \\Illuminate\\Support\\ServiceProvider {}');
        $zip->addFromString('public/fixture.css', $version);
        $zip->close();

        return new UploadedFile($path, 'fixture.zip', 'application/zip', null, true);
    }

    public function test_update_uses_same_volume_staging_and_removes_backups_only_after_success(): void
    {
        $installer = app(ModuleInstallerService::class);
        $module = $installer->installFromZip($this->zip('1.0.0'));
        $updated = $installer->updateFromZip($module, $this->zip('1.0.1'));
        $this->assertSame('1.0.1', $updated->version);
        $this->assertSame('1.0.1', file_get_contents(public_path('modules/audit--update-fixture/fixture.css')));
        $this->assertSame([], glob($this->directory.'/modules/.*.backup-*'));
        $this->assertSame([], glob($this->directory.'/modules/.*.stage-*'));
    }

    public function test_failed_publication_restores_module_code_assets_and_database_version(): void
    {
        $module = app(ModuleInstallerService::class)->installFromZip($this->zip('1.0.0'));
        $publisher = new class extends ModulePublicAssetsPublisherService
        {
            private int $calls = 0;

            public function publishFromInstallPath(string $installPath, string $moduleKey): void
            {
                if (++$this->calls === 1) {
                    throw new RuntimeException('Injected publication failure');
                }
                parent::publishFromInstallPath($installPath, $moduleKey);
            }
        };
        $installer = new ModuleInstallerService(app(ModuleManifestParserService::class), app(ModuleCacheService::class), app(PageCacheService::class), $publisher);
        try {
            $installer->updateFromZip($module, $this->zip('1.0.1'));
            $this->fail('Expected update failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected publication failure', $exception->getMessage());
        }
        $this->assertSame('1.0.0', $module->fresh()->version);
        $this->assertSame('1.0.0', json_decode(file_get_contents($module->install_path.'/module.json'), true)['version']);
        $this->assertSame('1.0.0', file_get_contents(public_path('modules/audit--update-fixture/fixture.css')));
        $this->assertSame([], glob($this->directory.'/modules/.*.backup-*'));
        $this->assertSame([], glob($this->directory.'/modules/.*.stage-*'));
    }
}
