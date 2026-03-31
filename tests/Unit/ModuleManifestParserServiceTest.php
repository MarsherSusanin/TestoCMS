<?php

namespace Tests\Unit;

use App\Modules\Extensibility\Services\ModuleManifestParserService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleManifestParserServiceTest extends TestCase
{
    private string $moduleRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleRoot = storage_path('framework/testing/module-manifest-parser');
        File::deleteDirectory($this->moduleRoot);
        File::ensureDirectoryExists($this->moduleRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->moduleRoot);

        parent::tearDown();
    }

    public function test_parser_preserves_optional_admin_nav_icon_without_whitelist_validation(): void
    {
        File::put($this->moduleRoot.'/module.json', json_encode([
            'id' => 'acme/content-tools',
            'name' => 'Acme Content Tools',
            'version' => '1.0.0',
            'provider' => 'Acme\\ContentTools\\ModuleServiceProvider',
            'autoload' => [
                'psr-4' => [
                    'Acme\\ContentTools\\' => 'src/',
                ],
            ],
            'admin' => [
                'nav' => [[
                    'label' => 'Content Tools',
                    'route' => 'module.acme_content_tools.index',
                    'icon' => 'custom-thing',
                    'short_ru' => 'CT',
                    'short_en' => 'CT',
                ]],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $manifest = app(ModuleManifestParserService::class)->parseFromDirectory($this->moduleRoot);

        $this->assertSame('custom-thing', $manifest->adminNav[0]['icon']);
    }
}
