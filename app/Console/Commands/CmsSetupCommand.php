<?php

namespace App\Console\Commands;

use App\Modules\Setup\Services\DeploymentProfileService;
use App\Modules\Setup\Services\EnvWriterService;
use App\Modules\Setup\Services\SetupFinalizationService;
use App\Modules\Setup\Services\SystemCheckService;
use Illuminate\Console\Command;

class CmsSetupCommand extends Command
{
    protected $signature = 'cms:setup {--redo : Re-run setup even if already installed}';

    protected $description = 'Interactive first-time setup wizard for TestoCMS';

    public function handle(
        SystemCheckService $systemCheck,
        DeploymentProfileService $deploymentProfiles,
        EnvWriterService $envWriter,
        SetupFinalizationService $setupFinalizer,
    ): int {
        $this->newLine();
        $this->components->info('TestoCMS — Мастер настройки');
        $this->newLine();

        // Check if already installed
        if (EnvWriterService::isInstalled() && ! $this->option('redo')) {
            $this->components->warn('CMS уже установлена. Используйте --redo для повторной настройки.');

            return self::SUCCESS;
        }

        if ($this->option('redo')) {
            if (! $this->confirm('Текущая конфигурация .env будет перезаписана. Продолжить?')) {
                return self::SUCCESS;
            }
            EnvWriterService::removeInstalledMarker();
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

        $result = $setupFinalizer->finalize($envData);

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
}
