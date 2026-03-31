<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Setup\Services\EnvWriterService;
use App\Modules\Setup\Services\SetupFinalizationService;
use App\Modules\Setup\Services\SystemCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private string $installedMarkerPath;

    private bool $installedMarkerExisted = false;

    private ?string $installedMarkerContents = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installedMarkerPath = storage_path('installed');
        $this->installedMarkerExisted = file_exists($this->installedMarkerPath);
        $this->installedMarkerContents = $this->installedMarkerExisted
            ? file_get_contents($this->installedMarkerPath) ?: null
            : null;

        EnvWriterService::removeInstalledMarker();
    }

    protected function tearDown(): void
    {
        if ($this->installedMarkerExisted) {
            file_put_contents($this->installedMarkerPath, $this->installedMarkerContents ?? '');
        } else {
            EnvWriterService::removeInstalledMarker();
        }

        parent::tearDown();
    }

    public function test_step2_defaults_to_postgresql_when_only_pgsql_driver_is_available(): void
    {
        $this->app->instance(SystemCheckService::class, new class extends SystemCheckService
        {
            public function autoDetect(): array
            {
                return [
                    'php_version' => PHP_VERSION,
                    'server_software' => 'testing',
                    'https' => false,
                    'app_url' => 'http://localhost',
                    'timezone' => 'UTC',
                    'has_pgsql' => true,
                    'has_mysql' => false,
                    'queue_connection' => 'sync',
                ];
            }
        });

        $this->get(route('setup.step2'))
            ->assertOk()
            ->assertDontSee('MySQL / MariaDB', false)
            ->assertSee('option value="pgsql" selected', false)
            ->assertSee('name="db_host" id="db_host" value="db"', false)
            ->assertSee('name="db_port" id="db_port" value="5432"', false)
            ->assertSee('name="db_database"', false)
            ->assertSee('name="db_username"', false);
    }

    public function test_setup_step2_is_not_full_page_cached(): void
    {
        $this->assertSame(0, User::query()->count());

        $this->get(route('setup.step2'))
            ->assertOk()
            ->assertHeaderMissing('X-TestoCMS-Cache');

        $this->get(route('setup.step2'))
            ->assertOk()
            ->assertHeaderMissing('X-TestoCMS-Cache');

        $this->assertSame(0, User::query()->count());
    }

    public function test_step5_uses_shared_finalizer_with_explicit_wizard_payload(): void
    {
        $this->app->instance(SystemCheckService::class, new class extends SystemCheckService
        {
            public function autoDetect(): array
            {
                return [
                    'php_version' => PHP_VERSION,
                    'server_software' => 'testing',
                    'https' => false,
                    'app_url' => 'http://localhost:8080',
                    'timezone' => 'UTC',
                    'has_pgsql' => true,
                    'has_mysql' => false,
                    'queue_connection' => 'sync',
                ];
            }
        });

        $finalizer = Mockery::mock(SetupFinalizationService::class);
        $finalizer->shouldReceive('finalize')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                return ($data['db_connection'] ?? null) === 'pgsql'
                    && ($data['db_host'] ?? null) === 'db'
                    && ($data['db_database'] ?? null) === 'testocms'
                    && ($data['deployment_profile'] ?? null) === 'shared_hosting'
                    && ($data['app_name'] ?? null) === 'Wizard Site'
                    && ($data['default_locale'] ?? null) === 'ru'
                    && ($data['admin_email'] ?? null) === 'radaevir@gmail.com'
                    && ($data['admin_login'] ?? null) === 'wizard_admin'
                    && ($data['admin_password'] ?? null) === 'Secret123!';
            }))
            ->andReturn([
                'steps' => [
                    ['label' => '.env', 'ok' => true],
                    ['label' => 'Admin account', 'ok' => true],
                ],
                'errors' => [],
                'hasErrors' => false,
            ]);

        $this->app->instance(SetupFinalizationService::class, $finalizer);

        $this->withSession([
            'setup.db' => [
                'db_connection' => 'pgsql',
                'db_host' => 'db',
                'db_port' => '5432',
                'db_database' => 'testocms',
                'db_username' => 'testocms',
                'db_password' => 'testocms',
            ],
            'setup.site' => [
                'deployment_profile' => 'shared_hosting',
                'app_name' => 'Wizard Site',
                'app_url' => 'http://localhost:8080',
                'supported_locales' => ['ru', 'en'],
                'default_locale' => 'ru',
            ],
            'setup.admin' => [
                'admin_name' => 'Admin',
                'admin_login' => 'wizard_admin',
                'admin_email' => 'radaevir@gmail.com',
                'admin_password' => 'Secret123!',
            ],
        ])->get(route('setup.step5'))
            ->assertOk()
            ->assertSee('Установка завершена', false)
            ->assertSessionMissing('setup.db')
            ->assertSessionMissing('setup.site')
            ->assertSessionMissing('setup.admin');
    }

    public function test_step3_defaults_to_shared_hosting_profile(): void
    {
        $response = $this->withSession([
            'setup.db' => [
                'db_connection' => 'pgsql',
                'db_host' => 'db',
                'db_port' => '5432',
                'db_database' => 'testocms',
                'db_username' => 'testocms',
                'db_password' => 'testocms',
            ],
        ])->get(route('setup.step3'));

        $response
            ->assertOk()
            ->assertSee('value="shared_hosting"', false)
            ->assertSee('Shared hosting (рекомендуется)', false)
            ->assertSee('value="docker_vps"', false);

        $this->assertSame(1, preg_match(
            '/id="deployment_profile_shared_hosting"[^>]*value="shared_hosting"[^>]*checked/s',
            $response->getContent()
        ));
    }
}
