<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Services\AdminProvisionerService;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Services\SlugResolverService;
use App\Modules\Setup\Services\EnvWriterService;
use App\Modules\Setup\Services\SetupFinalizationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class SetupFinalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, string|null>
     */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->captureEnv([
            'APP_ENV',
            'APP_NAME',
            'APP_URL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'CMS_ADMIN_NAME',
            'CMS_ADMIN_LOGIN',
            'CMS_ADMIN_EMAIL',
            'CMS_ADMIN_PASSWORD',
            'CMS_SEED_DEMO_CONTENT',
            'CMS_DEPLOYMENT_PROFILE',
            'QUEUE_CONNECTION',
            'CACHE_STORE',
        ]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        $this->restoreEnv([
            'APP_ENV',
            'APP_NAME',
            'APP_URL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'CMS_ADMIN_NAME',
            'CMS_ADMIN_LOGIN',
            'CMS_ADMIN_EMAIL',
            'CMS_ADMIN_PASSWORD',
            'CMS_SEED_DEMO_CONTENT',
            'CMS_DEPLOYMENT_PROFILE',
            'QUEUE_CONNECTION',
            'CACHE_STORE',
        ]);

        parent::tearDown();
    }

    public function test_finalizer_replaces_stale_default_admin_with_explicit_setup_credentials(): void
    {
        $this->setEnv([
            'CMS_ADMIN_NAME' => 'Super Admin',
            'CMS_ADMIN_LOGIN' => 'admin',
            'CMS_ADMIN_EMAIL' => 'admin@testocms.local',
            'CMS_ADMIN_PASSWORD' => 'ChangeMe123!',
        ]);

        app(RolesAndPermissionsSeeder::class)->run();
        app(AdminProvisionerService::class)->provisionFromEnvironment();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@testocms.local',
            'login' => 'admin',
        ]);

        $result = app(SetupFinalizationService::class)->finalize([
            'db_connection' => 'pgsql',
            'db_host' => 'db',
            'db_port' => '5432',
            'db_database' => 'testocms',
            'db_username' => 'testocms',
            'db_password' => 'testocms',
            'app_name' => 'Wizard Site',
            'app_url' => 'http://localhost:8080',
            'deployment_profile' => 'shared_hosting',
            'supported_locales' => ['ru', 'en'],
            'default_locale' => 'ru',
            'admin_name' => 'Admin',
            'admin_login' => 'wizard_admin',
            'admin_email' => 'radaevir@gmail.com',
            'admin_password' => 'Secret123!',
        ], [
            'write_env' => false,
            'apply_runtime_database' => false,
            'run_migrations' => false,
            'storage_link' => false,
            'optimize' => false,
            'mark_installed' => false,
        ]);

        $this->assertFalse($result['hasErrors']);
        $this->assertDatabaseMissing('users', [
            'email' => 'admin@testocms.local',
        ]);

        $admin = User::query()->where('email', 'radaevir@gmail.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('wizard_admin', $admin->login);
        $this->assertSame('active', $admin->status);
        $this->assertTrue($admin->hasRole('superadmin'));
        $this->assertTrue(Hash::check('Secret123!', (string) $admin->password));

        $this->post('/admin/login', [
            'email' => 'radaevir@gmail.com',
            'password' => 'Secret123!',
        ])->assertRedirect('/admin');
    }

    public function test_finalizer_overrides_stale_process_environment_before_follow_up_steps(): void
    {
        $this->setEnv([
            'APP_NAME' => 'Old Site',
            'APP_URL' => 'http://legacy.local',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'legacy',
            'DB_USERNAME' => 'legacy',
            'DB_PASSWORD' => 'legacy',
            'CMS_ADMIN_NAME' => 'Legacy Admin',
            'CMS_ADMIN_LOGIN' => 'legacy_admin',
            'CMS_ADMIN_EMAIL' => 'legacy@testocms.local',
            'CMS_ADMIN_PASSWORD' => 'LegacyPass123!',
            'CMS_DEPLOYMENT_PROFILE' => 'docker_vps',
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'redis',
        ]);

        $payload = [
            'deployment_profile' => 'shared_hosting',
            'db_connection' => 'pgsql',
            'db_host' => 'db',
            'db_port' => '5432',
            'db_database' => 'testocms',
            'db_username' => 'testocms',
            'db_password' => 'testocms',
            'app_name' => 'Wizard Site',
            'app_url' => 'http://localhost:8080',
            'supported_locales' => ['ru', 'en'],
            'default_locale' => 'ru',
            'admin_name' => 'Admin',
            'admin_login' => 'wizard_admin',
            'admin_email' => 'radaevir@gmail.com',
            'admin_password' => 'Secret123!',
        ];

        $realEnvWriter = app(EnvWriterService::class);
        $envWriter = Mockery::mock(EnvWriterService::class);
        $envWriter->shouldReceive('buildEnvContent')
            ->once()
            ->andReturn($realEnvWriter->buildEnvContent($payload));
        $envWriter->shouldReceive('writeEnvFile')
            ->once();

        $service = new SetupFinalizationService(
            $envWriter,
            app(AdminProvisionerService::class),
            app(PageCacheService::class),
            app(SlugResolverService::class),
        );

        $result = $service->finalize($payload, [
            'apply_runtime_database' => false,
            'run_migrations' => false,
            'storage_link' => false,
            'optimize' => false,
            'mark_installed' => false,
        ]);

        $this->assertFalse($result['hasErrors']);
        $this->assertSame('Wizard Site', getenv('APP_NAME'));
        $this->assertSame('http://localhost:8080', getenv('APP_URL'));
        $this->assertSame('db', getenv('DB_HOST'));
        $this->assertSame('testocms', getenv('DB_DATABASE'));
        $this->assertSame('radaevir@gmail.com', getenv('CMS_ADMIN_EMAIL'));
        $this->assertSame('Secret123!', getenv('CMS_ADMIN_PASSWORD'));
        $this->assertSame('shared_hosting', getenv('CMS_DEPLOYMENT_PROFILE'));
        $this->assertSame('sync', getenv('QUEUE_CONNECTION'));
        $this->assertSame('file', getenv('CACHE_STORE'));
    }

    /**
     * @param  array<string, string>  $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $this->originalEnv)) {
                $current = getenv($key);
                $this->originalEnv[$key] = $current === false ? null : $current;
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private function captureEnv(array $keys): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->originalEnv)) {
                continue;
            }

            $current = getenv($key);
            $this->originalEnv[$key] = $current === false ? null : $current;
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private function restoreEnv(array $keys): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $this->originalEnv)) {
                continue;
            }

            $value = $this->originalEnv[$key];

            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv($key.'='.$value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}
