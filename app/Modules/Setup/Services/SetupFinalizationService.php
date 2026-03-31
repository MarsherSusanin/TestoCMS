<?php

namespace App\Modules\Setup\Services;

use App\Modules\Auth\Services\AdminProvisionerService;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Services\SlugResolverService;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class SetupFinalizationService
{
    public function __construct(
        private readonly EnvWriterService $envWriter,
        private readonly AdminProvisionerService $adminProvisioner,
        private readonly PageCacheService $pageCacheService,
        private readonly SlugResolverService $slugResolverService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{write_env?: bool, apply_runtime_database?: bool, run_migrations?: bool, storage_link?: bool, optimize?: bool, mark_installed?: bool}  $options
     * @return array{steps: array<int, array{label: string, ok: bool}>, errors: array<int, string>, hasErrors: bool}
     */
    public function finalize(array $data, array $options = []): array
    {
        $steps = [];
        $errors = [];
        $envContent = null;

        $writeEnv = (bool) ($options['write_env'] ?? true);
        $applyRuntimeDatabase = (bool) ($options['apply_runtime_database'] ?? true);
        $runMigrations = (bool) ($options['run_migrations'] ?? true);
        $runStorageLink = (bool) ($options['storage_link'] ?? true);
        $optimize = (bool) ($options['optimize'] ?? true);
        $markInstalled = (bool) ($options['mark_installed'] ?? true);

        if ($writeEnv && ! $this->runCriticalStep($steps, $errors, '.env', function () use ($data, &$envContent): void {
            $envContent = $this->envWriter->buildEnvContent($data);
            $this->envWriter->writeEnvFile($envContent);
            $this->syncProcessEnvironmentFromEnvContent($envContent);
        })) {
            return $this->result($steps, $errors);
        }

        if ($applyRuntimeDatabase && ! $this->runCriticalStep($steps, $errors, 'Runtime database', function () use ($data): void {
            Artisan::call('config:clear');
            $this->applyRuntimeDatabaseConfiguration($data);
        })) {
            return $this->result($steps, $errors);
        }

        if ($runMigrations && ! $this->runCriticalStep($steps, $errors, 'Migrations', function (): void {
            Artisan::call('migrate', ['--force' => true]);
        })) {
            return $this->result($steps, $errors);
        }

        if (! $this->runCriticalStep($steps, $errors, 'Roles and permissions', function (): void {
            app(RolesAndPermissionsSeeder::class)->run();
        })) {
            return $this->result($steps, $errors);
        }

        if (! $this->runCriticalStep($steps, $errors, 'Admin account', function () use ($data): void {
            $this->adminProvisioner->provision([
                'name' => $data['admin_name'] ?? null,
                'login' => $data['admin_login'] ?? null,
                'email' => $data['admin_email'] ?? null,
                'password' => $data['admin_password'] ?? null,
                'status' => 'active',
            ], true);
        })) {
            return $this->result($steps, $errors);
        }

        if (! $this->runCriticalStep($steps, $errors, 'Demo content', function (): void {
            app(DemoContentSeeder::class)->run();
        })) {
            return $this->result($steps, $errors);
        }

        if ($runStorageLink) {
            $this->runNonCriticalStep($steps, 'Storage link', function (): void {
                Artisan::call('storage:link');
            });
        }

        if ($optimize) {
            $this->runNonCriticalStep($steps, 'Optimization', function (): void {
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                Artisan::call('view:cache');
            });
        }

        if ($markInstalled && empty($errors)) {
            $this->runCriticalStep($steps, $errors, 'Installed marker', function (): void {
                $this->envWriter->markInstalled();
            });
        }

        if (empty($errors)) {
            $this->pageCacheService->flushAll();
            $this->slugResolverService->flushAll();
        }

        return $this->result($steps, $errors);
    }

    /**
     * @param  array<int, array{label: string, ok: bool}>  $steps
     * @param  array<int, string>  $errors
     */
    private function runCriticalStep(array &$steps, array &$errors, string $label, callable $callback): bool
    {
        try {
            $callback();
            $steps[] = ['label' => $label, 'ok' => true];

            return true;
        } catch (Throwable $e) {
            $steps[] = ['label' => $label, 'ok' => false];
            $errors[] = $label.': '.$e->getMessage();

            return false;
        }
    }

    /**
     * @param  array<int, array{label: string, ok: bool}>  $steps
     */
    private function runNonCriticalStep(array &$steps, string $label, callable $callback): void
    {
        try {
            $callback();
            $steps[] = ['label' => $label, 'ok' => true];
        } catch (Throwable) {
            $steps[] = ['label' => $label, 'ok' => false];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyRuntimeDatabaseConfiguration(array $data): void
    {
        $driver = (string) ($data['db_connection'] ?? 'mysql');
        $connection = (array) config('database.connections.'.$driver, []);

        $overrides = [
            'host' => (string) ($data['db_host'] ?? $connection['host'] ?? 'localhost'),
            'port' => (string) ($data['db_port'] ?? $connection['port'] ?? ($driver === 'pgsql' ? '5432' : '3306')),
            'database' => (string) ($data['db_database'] ?? $connection['database'] ?? ''),
            'username' => (string) ($data['db_username'] ?? $connection['username'] ?? ''),
            'password' => (string) ($data['db_password'] ?? $connection['password'] ?? ''),
        ];

        if ($driver === 'pgsql') {
            $overrides['search_path'] = (string) ($data['db_schema'] ?? $connection['search_path'] ?? 'public');
            $overrides['sslmode'] = (string) ($data['db_sslmode'] ?? $connection['sslmode'] ?? 'prefer');
        }

        config([
            'database.default' => $driver,
            'database.connections.'.$driver => array_merge($connection, $overrides),
        ]);

        DB::purge($driver);
        DB::setDefaultConnection($driver);
        DB::reconnect($driver);
    }

    private function syncProcessEnvironmentFromEnvContent(string $envContent): void
    {
        $resolved = [];

        foreach (preg_split('/\R/u', $envContent) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separatorPosition = strpos($line, '=');
            if ($separatorPosition === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separatorPosition));
            if ($key === '') {
                continue;
            }

            $value = $this->resolveEnvValue(substr($line, $separatorPosition + 1), $resolved);

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            $resolved[$key] = $value;
        }
    }

    /**
     * @param  array<string, string>  $resolved
     */
    private function resolveEnvValue(string $rawValue, array $resolved): string
    {
        $value = trim($rawValue);

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === '\'' && str_ends_with($value, '\'')))
        ) {
            $quote = $value[0];
            $value = substr($value, 1, -1);

            if ($quote === '"') {
                $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
            }
        }

        return (string) preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', static function (array $matches) use ($resolved): string {
            $variable = $matches[1] ?? '';
            $current = $resolved[$variable] ?? getenv($variable);

            return $current === false ? '' : (string) $current;
        }, $value);
    }

    /**
     * @param  array<int, array{label: string, ok: bool}>  $steps
     * @param  array<int, string>  $errors
     * @return array{steps: array<int, array{label: string, ok: bool}>, errors: array<int, string>, hasErrors: bool}
     */
    private function result(array $steps, array $errors): array
    {
        return [
            'steps' => $steps,
            'errors' => $errors,
            'hasErrors' => $errors !== [],
        ];
    }
}
