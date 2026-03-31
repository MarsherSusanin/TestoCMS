<?php

namespace Tests\Feature;

use App\Models\CmsModule;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Modules\Extensibility\Services\EnabledModulePublicRoutesLoader;
use App\Modules\Extensibility\Services\ModuleRuntimeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccessibilityModuleTest extends TestCase
{
    use RefreshDatabase;

    private string $testingModulesRoot;

    private string $testingCacheFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testingModulesRoot = storage_path('framework/testing/modules');
        $this->testingCacheFile = storage_path('framework/testing/cms_modules.php');

        File::deleteDirectory($this->testingModulesRoot);
        File::deleteDirectory(public_path('modules/testocms--accessibility'));
        $this->deleteFileIfExists($this->testingCacheFile);
        $this->deleteFileIfExists(base_path('bootstrap/cache/cms_modules.php'));
        EnabledModulePublicRoutesLoader::reset();

        config()->set('modules.modules_root', $this->testingModulesRoot);
        config()->set('modules.cache_file', $this->testingCacheFile);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testingModulesRoot);
        File::deleteDirectory(public_path('modules/testocms--accessibility'));
        $this->deleteFileIfExists($this->testingCacheFile);
        $this->deleteFileIfExists(base_path('bootstrap/cache/cms_modules.php'));
        EnabledModulePublicRoutesLoader::reset();

        parent::tearDown();
    }

    public function test_accessibility_module_is_listed_and_injects_public_shell_after_activation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('accessibility-admin@testocms.local', 'superadmin');
        $this->seedPublicContent();
        $tablesBefore = Schema::getTableListing();
        sort($tablesBefore);

        $this->actingAs($user)
            ->get('/admin/modules')
            ->assertOk()
            ->assertSee('Accessibility')
            ->assertSee('Установить bundled-модуль');

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('data-a11y-toggle', false)
            ->assertDontSee('data-a11y-ribbon', false)
            ->assertDontSee('modules/testocms--accessibility/accessibility-public.js', false);

        $this->actingAs($user)
            ->post('/admin/modules/install-bundled/testocms--accessibility', [
                'activate_now' => 1,
            ])
            ->assertRedirect('/admin/modules');

        $this->assertDatabaseHas('cms_modules', [
            'module_key' => 'testocms/accessibility',
            'enabled' => 1,
        ]);
        $this->assertFileExists(public_path('modules/testocms--accessibility/accessibility-public.js'));

        $tablesAfter = Schema::getTableListing();
        sort($tablesAfter);
        $this->assertSame($tablesBefore, $tablesAfter);

        $this->get('/en')
            ->assertOk()
            ->assertSee('data-a11y-toggle', false)
            ->assertSee('data-a11y-ribbon', false)
            ->assertSee('window.TestoCmsAccessibilityBoot', false)
            ->assertSee('modules/testocms--accessibility/accessibility-public.js', false);

        $this->get('/ru')
            ->assertOk()
            ->assertSee('data-a11y-toggle', false)
            ->assertSee('data-a11y-ribbon', false);

        $this->get('/en/blog/hello')
            ->assertOk()
            ->assertSee('data-a11y-toggle', false)
            ->assertSee('data-a11y-ribbon', false);
    }

    public function test_accessibility_module_flushes_guest_page_cache_on_activate_and_deactivate(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('accessibility-cache@testocms.local', 'superadmin');
        $this->seedPublicContent();

        $this->get('/en')
            ->assertOk()
            ->assertHeader('X-TestoCMS-Cache', 'MISS')
            ->assertDontSee('data-a11y-toggle', false);

        $this->get('/en')
            ->assertOk()
            ->assertHeader('X-TestoCMS-Cache', 'HIT')
            ->assertDontSee('data-a11y-toggle', false);

        $this->actingAs($user)
            ->post('/admin/modules/install-bundled/testocms--accessibility', [
                'activate_now' => 1,
            ])
            ->assertRedirect('/admin/modules');

        $this->resetModuleRuntimeBootstrapState();
        EnabledModulePublicRoutesLoader::reset();

        $this->withFreshApplicationPreservingInMemoryDatabase(function (): void {
            $this->get('/en')
                ->assertOk()
                ->assertHeader('X-TestoCMS-Cache', 'MISS')
                ->assertSee('data-a11y-toggle', false);

            $this->get('/en')
                ->assertOk()
                ->assertHeader('X-TestoCMS-Cache', 'HIT')
                ->assertSee('data-a11y-toggle', false);
        });

        $module = CmsModule::query()->where('module_key', 'testocms/accessibility')->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.modules.deactivate', $module))
            ->assertRedirect('/admin/modules');

        $this->resetModuleRuntimeBootstrapState();
        EnabledModulePublicRoutesLoader::reset();

        $this->withFreshApplicationPreservingInMemoryDatabase(function (): void {
            $this->get('/en')
                ->assertOk()
                ->assertHeader('X-TestoCMS-Cache', 'MISS')
                ->assertDontSee('data-a11y-toggle', false)
                ->assertDontSee('modules/testocms--accessibility/accessibility-public.js', false);
        });
    }

    private function seedPublicContent(): void
    {
        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Home',
            'slug' => 'home',
            'content_blocks' => [],
            'rendered_html' => '<p>Accessible home</p>',
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'ru',
            'title' => 'Главная',
            'slug' => 'home',
            'content_blocks' => [],
            'rendered_html' => '<p>Доступная главная</p>',
        ]);

        $post = Post::query()->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Hello',
            'slug' => 'hello',
            'content_html' => '<p>Hello accessibility</p>',
            'content_plain' => 'Hello accessibility',
        ]);
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'login' => str_replace('@testocms.local', '', $email),
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function resetModuleRuntimeBootstrapState(): void
    {
        $reflection = new \ReflectionClass(ModuleRuntimeService::class);

        $autoloadPrefixes = $reflection->getProperty('autoloadPrefixes');
        $autoloadPrefixes->setAccessible(true);
        $autoloadPrefixes->setValue(null, []);

        $autoloadRegistered = $reflection->getProperty('autoloadRegistered');
        $autoloadRegistered->setAccessible(true);
        $autoloadRegistered->setValue(null, false);
    }

    private function withFreshApplicationPreservingInMemoryDatabase(callable $callback): void
    {
        $originalApp = $this->app;
        $originalContainer = Container::getInstance();
        $originalFacadeApplication = Facade::getFacadeApplication();
        $originalConnectionResolver = Model::getConnectionResolver();
        $originalConnection = $originalApp->make('db')->connection();
        $pdo = $originalConnection->getPdo();
        $readPdo = $originalConnection->getReadPdo() ?: $pdo;

        $freshApp = require base_path('bootstrap/app.php');
        $freshApp->resolving('config', function ($config): void {
            $config->set('modules.modules_root', $this->testingModulesRoot);
            $config->set('modules.cache_file', $this->testingCacheFile);
        });
        $freshApp->resolving('db', function ($database) use ($pdo, $readPdo): void {
            $connection = $database->connection();
            $connection->setPdo($pdo);
            $connection->setReadPdo($readPdo);
        });
        $freshApp->make(ConsoleKernel::class)->bootstrap();

        $this->app = $freshApp;
        Container::setInstance($freshApp);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($freshApp);
        Model::setConnectionResolver($freshApp->make('db'));

        try {
            $callback();
        } finally {
            $this->app = $originalApp;
            Container::setInstance($originalContainer);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($originalFacadeApplication);
            Model::setConnectionResolver($originalConnectionResolver);
        }
    }

    private function deleteFileIfExists(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
