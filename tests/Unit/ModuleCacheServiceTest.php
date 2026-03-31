<?php

namespace Tests\Unit;

use App\Models\CmsModule;
use App\Modules\Extensibility\Services\ModuleCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ModuleCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $cacheDirectory;

    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDirectory = storage_path('framework/testing/module-cache-service');
        $this->cachePath = $this->cacheDirectory.'/cms_modules.php';

        File::deleteDirectory($this->cacheDirectory);
        File::ensureDirectoryExists($this->cacheDirectory);

        config()->set('modules.cache_file', $this->cachePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cacheDirectory);

        parent::tearDown();
    }

    public function test_load_enabled_modules_returns_cached_snapshot_when_database_access_would_fail(): void
    {
        $cachedModules = [$this->moduleRow('testocms/booking')];
        app(ModuleCacheService::class)->writeCacheFile($cachedModules);

        Schema::shouldReceive('hasTable')->andThrow(new RuntimeException('DB unavailable.'));

        $loadedModules = app(ModuleCacheService::class)->loadEnabledModules();

        $this->assertSame($cachedModules, $loadedModules);
    }

    public function test_load_enabled_modules_self_heals_empty_cache_file_from_database(): void
    {
        CmsModule::query()->create([
            'module_key' => 'testocms/booking',
            'name' => 'Booking',
            'version' => '1.0.0',
            'install_path' => base_path('modules/testocms--booking'),
            'provider' => 'TestoCms\\Booking\\ModuleServiceProvider',
            'enabled' => true,
            'status' => 'enabled',
            'metadata' => [
                'autoload' => [
                    'psr-4' => [
                        'TestoCms\\Booking\\' => 'src/',
                    ],
                ],
            ],
        ]);

        app(ModuleCacheService::class)->writeCacheFile([]);

        $loadedModules = app(ModuleCacheService::class)->loadEnabledModules();

        $this->assertCount(1, $loadedModules);
        $this->assertSame('testocms/booking', $loadedModules[0]['module_key']);
        $this->assertSame('testocms/booking', $this->readCachedModules()[0]['module_key'] ?? null);
    }

    public function test_load_enabled_modules_ignores_stale_cache_when_database_snapshot_is_empty(): void
    {
        $cachedModules = [$this->moduleRow('testocms/booking')];
        app(ModuleCacheService::class)->writeCacheFile($cachedModules);

        $loadedModules = app(ModuleCacheService::class)->loadEnabledModules();

        $this->assertSame([], $loadedModules);
        $this->assertSame([], $this->readCachedModules());
    }

    public function test_rebuild_from_database_does_not_overwrite_existing_cache_when_database_lookup_fails(): void
    {
        $cachedModules = [$this->moduleRow('testocms/booking')];
        app(ModuleCacheService::class)->writeCacheFile($cachedModules);

        Schema::shouldReceive('hasTable')->once()->andThrow(new RuntimeException('DB unavailable.'));

        try {
            app(ModuleCacheService::class)->rebuildFromDatabase();
            $this->fail('Expected rebuildFromDatabase to throw when database access fails.');
        } catch (RuntimeException $e) {
            $this->assertSame('DB unavailable.', $e->getMessage());
        }

        $this->assertSame($cachedModules, $this->readCachedModules());
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleRow(string $moduleKey): array
    {
        return [
            'module_key' => $moduleKey,
            'provider' => 'TestoCms\\Booking\\ModuleServiceProvider',
            'install_path' => base_path('modules/testocms--booking'),
            'version' => '1.0.0',
            'metadata' => [
                'autoload' => [
                    'psr-4' => [
                        'TestoCms\\Booking\\' => 'src/',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCachedModules(): array
    {
        /** @var array{modules?: array<int, array<string, mixed>>} $payload */
        $payload = require $this->cachePath;

        return is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
    }
}
