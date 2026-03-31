<?php

namespace Tests\Feature;

use App\Modules\Extensibility\Services\ModuleCacheService;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BuildModulesCacheCommandTest extends TestCase
{
    private string $cacheDirectory;

    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDirectory = storage_path('framework/testing/build-modules-cache-command');
        $this->cachePath = $this->cacheDirectory.'/cms_modules.php';

        File::deleteDirectory($this->cacheDirectory);
        File::ensureDirectoryExists($this->cacheDirectory);
        File::put($this->cachePath, '<?php return '.var_export([
            'generated_at' => now()->toIso8601String(),
            'modules' => [[
                'module_key' => 'testocms/booking',
                'provider' => 'TestoCms\\Booking\\ModuleServiceProvider',
                'install_path' => base_path('modules/testocms--booking'),
                'version' => '1.0.0',
                'metadata' => [],
            ]],
        ], true).';'.PHP_EOL);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cacheDirectory);

        parent::tearDown();
    }

    public function test_command_fails_without_claiming_success_when_rebuild_throws(): void
    {
        $moduleCache = Mockery::mock(ModuleCacheService::class);
        $moduleCache->shouldReceive('rebuildFromDatabase')
            ->once()
            ->andThrow(new RuntimeException('DB unavailable.'));
        $moduleCache->shouldReceive('cachePath')
            ->once()
            ->andReturn($this->cachePath);

        $this->app->instance(ModuleCacheService::class, $moduleCache);

        $this->artisan('cms:modules:cache')
            ->expectsOutput('Modules cache rebuild failed. Existing cache file was left untouched: '.$this->cachePath)
            ->expectsOutput('Error: DB unavailable.')
            ->doesntExpectOutput('Modules cache rebuilt: 0 enabled module(s).')
            ->assertExitCode(1);

        /** @var array{modules?: array<int, array<string, mixed>>} $payload */
        $payload = require $this->cachePath;

        $this->assertSame('testocms/booking', $payload['modules'][0]['module_key'] ?? null);
    }
}
