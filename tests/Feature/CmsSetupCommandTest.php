<?php

namespace Tests\Feature;

use App\Modules\Setup\Services\EnvWriterService;
use App\Modules\Setup\Services\SetupFinalizationService;
use App\Modules\Setup\Services\SystemCheckService;
use Mockery;
use Tests\TestCase;

class CmsSetupCommandTest extends TestCase
{
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

    public function test_command_passes_explicit_credentials_to_shared_finalizer(): void
    {
        $this->app->instance(SystemCheckService::class, new class extends SystemCheckService
        {
            public function runAll(): array
            {
                return [
                    'php_version' => [
                        'passed' => true,
                        'label' => 'PHP ≥ 8.2',
                        'detail' => PHP_VERSION,
                        'optional' => false,
                    ],
                ];
            }

            public function allRequiredPassed(): bool
            {
                return true;
            }

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

        $envWriter = Mockery::mock(EnvWriterService::class);
        $envWriter->shouldReceive('testDatabaseConnection')
            ->once()
            ->with(Mockery::on(function (array $params): bool {
                return ($params['driver'] ?? null) === 'pgsql'
                    && ($params['host'] ?? null) === 'db'
                    && ($params['database'] ?? null) === 'testocms'
                    && ($params['username'] ?? null) === 'testocms'
                    && ($params['password'] ?? null) === 'testocms';
            }))
            ->andReturn(['ok' => true, 'error' => null]);
        $this->app->instance(EnvWriterService::class, $envWriter);

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
                    && ($data['admin_name'] ?? null) === 'Admin'
                    && ($data['admin_login'] ?? null) === 'wizard_admin'
                    && ($data['admin_email'] ?? null) === 'radaevir@gmail.com'
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

        $this->artisan('cms:setup')
            ->expectsQuestion('Хост БД', 'db')
            ->expectsQuestion('Порт', '5432')
            ->expectsQuestion('Имя базы данных', 'testocms')
            ->expectsQuestion('Пользователь БД', 'testocms')
            ->expectsQuestion('Пароль БД', 'testocms')
            ->expectsChoice('Профиль размещения', 'shared_hosting', ['shared_hosting', 'docker_vps'])
            ->expectsQuestion('Название сайта', 'Wizard Site')
            ->expectsQuestion('URL сайта', 'http://localhost:8080')
            ->expectsConfirmation('Включить русский язык?', true)
            ->expectsConfirmation('Включить английский?', true)
            ->expectsQuestion('Имя администратора', 'Admin')
            ->expectsQuestion('Логин', 'wizard_admin')
            ->expectsQuestion('Email', 'radaevir@gmail.com')
            ->expectsQuestion('Пароль (мин. 8 символов)', 'Secret123!')
            ->assertExitCode(0);
    }
}
