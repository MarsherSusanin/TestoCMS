<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Setup\Services\DeploymentProfileService;
use App\Modules\Setup\Services\EnvWriterService;
use App\Modules\Setup\Services\InstallationIdentityService;
use App\Modules\Setup\Services\SetupFinalizationService;
use App\Modules\Setup\Services\SetupLockService;
use App\Modules\Setup\Services\SetupRedoService;
use App\Modules\Setup\Services\SystemCheckService;
use App\Modules\Updates\Services\UpdateOperationGate;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CmsSetupCommand extends Command
{
    protected $signature = 'cms:setup {--redo : Change settings/admin of the current instance only} {--from-env : Use configured environment without writing .env} {--force : Explicitly authorize noninteractive redo or adoption} {--adopt-existing : Bind a verified legacy database/storage without reprovisioning}';

    protected $description = 'Interactive first-time setup wizard for TestoCMS';

    public function handle(
        SystemCheckService $systemCheck,
        DeploymentProfileService $deploymentProfiles,
        EnvWriterService $envWriter,
        SetupFinalizationService $setupFinalizer,
        SetupLockService $lock,
        InstallationIdentityService $identity,
        SetupRedoService $redo,
        UpdateOperationGate $gate,
    ): int {
        $this->newLine();
        $this->components->info('TestoCMS — Мастер настройки');
        $this->newLine();

        if ($this->option('adopt-existing')) {
            if (! $this->option('force') || $this->option('redo') || $this->option('from-env')) {
                $this->error('Adoption requires --adopt-existing --force without --redo/--from-env.');

                return self::FAILURE;
            }
            try {
                $gate->runExclusive(function () use ($identity, $envWriter, $redo): void {
                    if (is_file($redo->journalPath())) {
                        throw new \RuntimeException('Run cms:setup:recover before adoption.');
                    }
                    $identity->assertSchema();
                    $key = (string) config('app.key');
                    $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
                    if ($decoded === false || ! Encrypter::supported($decoded, (string) config('app.cipher'))) {
                        throw new \RuntimeException('Adoption requires the existing valid APP_KEY.');
                    }
                    if (EnvWriterService::isInstalled()) {
                        $marker = json_decode((string) file_get_contents(storage_path('installed')), true);
                        if (! empty($marker['instance_id'])) {
                            $identity->assertReady();

                            return;
                        }
                    }
                    if (is_file(app()->environmentFilePath())) {
                        $identity->assertEnvironmentIdentity(Dotenv::parse((string) file_get_contents(app()->environmentFilePath())));
                    }
                    $envWriter->markInstalled();
                });
                $this->info('Existing installation bound. Environment and administrator were preserved.');

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }
        if ($this->option('redo')) {
            if (! EnvWriterService::isInstalled()) {
                $this->error('Redo requires an installed instance. Use explicit adoption if its marker was lost.');

                return self::FAILURE;
            }
            if (! $this->input->isInteractive() && ! $this->option('force')) {
                $this->error('Noninteractive redo requires --force. No changes were made.');

                return self::FAILURE;
            }
            try {
                $identity->assertReady();
                if (! $this->option('force') && ! $this->confirm('Изменить настройки/admin текущей CMS? БД, APP_KEY и web root останутся прежними.')) {
                    return self::SUCCESS;
                }
                $data = $this->redoData();
                $this->line('Target: existing installation; admin '.$data['admin_email'].'. No migrations or content seeding.');
                $redo->redo($data, ! $this->option('from-env'), fn ($operation) => $this->line('Recovery operation: '.$operation));
                $this->info('Settings and administrator updated. Installation identity preserved.');

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }
        if (EnvWriterService::isInstalled()) {
            try {
                $identity->assertReady();
                $this->components->info('CMS already initialized; administrator/environment preserved.');

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        if ($this->option('from-env')) {
            return $this->fromEnvironment($systemCheck, $setupFinalizer, $lock);
        }

        // Step 1: System check
        $this->components->info('Шаг 1/5 — Проверка системы');
        $checks = $systemCheck->runAll();
        foreach ($checks as $check) {
            $icon = $check['passed'] ? '✓' : '✗';
            $style = $check['passed'] ? 'info' : (($check['optional'] ?? false) ? 'comment' : 'error');
            $this->line("  <{$style}>{$icon}</{$style}> {$check['label']} — {$check['detail']}");
        }
        $this->newLine();

        if (! $systemCheck->allRequiredPassed()) {
            $this->components->error('Не все обязательные требования выполнены.');

            return self::FAILURE;
        }

        $auto = $systemCheck->autoDetect();

        // Step 2: Database
        $this->components->info('Шаг 2/5 — База данных');

        $drivers = [];
        if ($auto['has_mysql']) {
            $drivers['mysql'] = 'mysql';
        }
        if ($auto['has_pgsql']) {
            $drivers['pgsql'] = 'pgsql';
        }

        $dbConnection = count($drivers) > 1
            ? $this->choice('Тип БД', array_keys($drivers), 'mysql')
            : array_key_first($drivers);

        $defaultConnectionConfig = (array) config('database.connections.'.$dbConnection, []);
        $defaultPort = (string) ($defaultConnectionConfig['port'] ?? ($dbConnection === 'pgsql' ? '5432' : '3306'));
        $defaultHost = (string) ($defaultConnectionConfig['host'] ?? 'localhost');
        $defaultDatabase = (string) ($defaultConnectionConfig['database'] ?? '');
        $defaultUsername = (string) ($defaultConnectionConfig['username'] ?? '');

        $dbHost = $this->ask('Хост БД', $defaultHost);
        $dbPort = $this->ask('Порт', $defaultPort);
        $dbDatabase = $this->ask('Имя базы данных', $defaultDatabase);
        $dbUsername = $this->ask('Пользователь БД', $defaultUsername);
        $dbPassword = $this->secret('Пароль БД') ?? '';

        // Test connection
        $this->output->write('  Проверка подключения... ');
        $testResult = $envWriter->testDatabaseConnection([
            'driver' => $dbConnection,
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbDatabase,
            'username' => $dbUsername,
            'password' => $dbPassword,
        ]);

        if (! $testResult['ok']) {
            $this->newLine();
            $this->components->error('Ошибка подключения: '.$testResult['error']);

            return self::FAILURE;
        }
        $this->line('<info>✓ OK</info>');
        $this->newLine();

        // Step 3: Site settings
        $this->components->info('Шаг 3/5 — Настройки сайта');

        $deploymentProfile = $this->choice(
            'Профиль размещения',
            $deploymentProfiles->keys(),
            $deploymentProfiles->default(),
        );

        $profileSummary = $deploymentProfiles->resolve($deploymentProfile);
        $this->line('  <comment>'.$deploymentProfile.'</comment> — '.$profileSummary['description']);
        $this->newLine();

        $appName = $this->ask('Название сайта', 'TestoCMS');
        $appUrl = $this->ask('URL сайта', $auto['app_url'] ?? 'https://localhost');

        $locales = [];
        if ($this->confirm('Включить русский язык?', true)) {
            $locales[] = 'ru';
        }
        if ($this->confirm('Включить английский?', true)) {
            $locales[] = 'en';
        }
        if (empty($locales)) {
            $locales = ['ru'];
        }

        $defaultLocale = count($locales) > 1
            ? $this->choice('Язык по умолчанию', $locales, $locales[0])
            : $locales[0];

        $this->newLine();

        // Step 4: Admin account
        $this->components->info('Шаг 4/5 — Администратор');

        $adminName = $this->ask('Имя администратора', 'Super Admin');
        $adminLogin = $this->ask('Логин', 'admin');
        $adminEmail = $this->ask('Email');

        do {
            $adminPassword = $this->secret('Пароль (мин. 8 символов)');
            if (strlen($adminPassword) < 8) {
                $this->components->warn('Пароль слишком короткий.');
            }
        } while (strlen($adminPassword) < 8);

        $this->newLine();

        // Step 5: Finalize
        $this->components->info('Шаг 5/5 — Установка');

        $envData = array_merge($auto, [
            'db_connection' => $dbConnection,
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_database' => $dbDatabase,
            'db_username' => $dbUsername,
            'db_password' => $dbPassword,
            'deployment_profile' => $deploymentProfile,
            'app_name' => $appName,
            'app_url' => $appUrl,
            'supported_locales' => $locales,
            'default_locale' => $defaultLocale,
            'admin_name' => $adminName,
            'admin_login' => $adminLogin,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
        ]);

        try {
            $result = $lock->run(fn (): array => $setupFinalizer->finalize($envData));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['steps'] as $step) {
            $icon = $step['ok'] ? '✓' : '✗';
            $style = $step['ok'] ? 'info' : 'comment';
            $this->line("  <{$style}>{$icon}</{$style}> {$step['label']}");
        }

        if ($result['hasErrors']) {
            foreach ($result['errors'] as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('🎉 TestoCMS успешно установлена!');
        $this->line('  Админка: <comment>'.$appUrl.'/admin/login</comment>');
        $this->line('  Email:   <comment>'.$adminEmail.'</comment>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function fromEnvironment(SystemCheckService $checks, SetupFinalizationService $finalizer, SetupLockService $lock): int
    {
        $driver = (string) config('database.default');
        $connection = (array) config('database.connections.'.$driver, []);
        $key = (string) config('app.key', '');
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if ($decoded === false || ! Encrypter::supported($decoded, (string) config('app.cipher', 'AES-256-CBC'))) {
            $this->components->error('A valid APP_KEY must be configured before --from-env initialization.');

            return self::FAILURE;
        }
        $data = [
            'db_connection' => $driver,
            'db_host' => $connection['host'] ?? '',
            'db_port' => $connection['port'] ?? '',
            'db_database' => $connection['database'] ?? '',
            'db_username' => $connection['username'] ?? '',
            'db_password' => $connection['password'] ?? '',
            'admin_name' => config('setup.admin.name'),
            'admin_login' => config('setup.admin.login'),
            'admin_email' => config('setup.admin.email'),
            'admin_password' => config('setup.admin.password'),
        ];
        $validation = Validator::make($data, [
            'db_connection' => 'required|in:mysql,pgsql,sqlite',
            'db_database' => 'required|string',
            'admin_name' => 'required|string|max:255',
            'admin_login' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8',
        ]);
        if ($validation->fails()) {
            $this->components->error('Environment initialization requires valid database and administrator settings: '.implode(' ', $validation->errors()->all()));

            return self::FAILURE;
        }
        if (app()->environment('production') && trim((string) config('cms.content_api.key', '')) === '') {
            $this->components->error('CMS_CONTENT_API_KEY must be configured before production initialization.');

            return self::FAILURE;
        }
        foreach ($checks->runAll() as $name => $check) {
            if ($name === 'env_writable' || ($name === 'pdo_database' && $driver === 'sqlite' && extension_loaded('pdo_sqlite'))) {
                continue;
            }
            if (! ($check['optional'] ?? false) && ! $check['passed']) {
                $this->components->error('Initialization requirement failed: '.$check['label']);

                return self::FAILURE;
            }
        }
        try {
            $result = $lock->run(function () use ($data, $finalizer): array {
                // Another initializer may have completed while this process booted.
                if (EnvWriterService::isInstalled() && ! $this->option('redo')) {
                    app(InstallationIdentityService::class)->assertReady();

                    return ['steps' => [], 'errors' => [], 'hasErrors' => false];
                }

                return $finalizer->finalize($data, ['write_env' => false, 'apply_runtime_database' => false]);
            });
        } catch (Throwable $e) {
            $this->components->error('Initialization failed: '.$e->getMessage());

            return self::FAILURE;
        }
        if ($result['hasErrors']) {
            foreach ($result['errors'] as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }
        $this->components->info('TestoCMS initialization complete. Environment file was preserved.');

        return self::SUCCESS;
    }

    private function redoData(): array
    {
        if ($this->option('from-env')) {
            $environment = Dotenv::parse((string) file_get_contents(app()->environmentFilePath()));

            return ['admin_name' => $environment['CMS_ADMIN_NAME'] ?? config('setup.admin.name'), 'admin_login' => $environment['CMS_ADMIN_LOGIN'] ?? config('setup.admin.login'),
                'admin_email' => $environment['CMS_ADMIN_EMAIL'] ?? config('setup.admin.email'), 'admin_password' => $environment['CMS_ADMIN_PASSWORD'] ?? config('setup.admin.password')];
        }
        $admin = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'superadmin'))->orderBy('id')->firstOrFail();

        return [
            'app_name' => $this->ask('Название сайта', config('app.name')),
            'app_url' => $this->ask('URL сайта', config('app.url')),
            'timezone' => $this->ask('Часовой пояс', config('app.timezone')),
            'admin_name' => $this->ask('Имя администратора', $admin->name),
            'admin_login' => $this->ask('Логин', $admin->login),
            'admin_email' => $this->ask('Email', $admin->email),
            'admin_password' => $this->secret('Новый пароль (мин. 8 символов)'),
        ];
    }
}
