<?php

namespace App\Modules\Setup\Services;

use App\Modules\Auth\Services\AdminProvisionerService;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Services\SlugResolverService;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Dotenv\Dotenv;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class SetupFinalizationService
{
    public function __construct(
        private readonly EnvWriterService $envWriter,
        private readonly AdminProvisionerService $adminProvisioner,
        private readonly PageCacheService $pageCacheService,
        private readonly SlugResolverService $slugResolverService,
    ) {}

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

        if ($runStorageLink && ! $this->runCriticalStep($steps, $errors, 'Web root and module directories', function () use ($writeEnv, $data): void {
            $path = $writeEnv ? (string) (Dotenv::parse($this->envWriter->buildEnvContent($data))['LARAVEL_PUBLIC_PATH'] ?? 'html_public') : public_path();
            $root = $this->isAbsolutePath($path) ? $path : base_path($path);
            app(PublicRootValidationService::class)->validate($root);
        })) {
            return $this->result($steps, $errors);
        }

        if ($writeEnv && ! $this->runCriticalStep($steps, $errors, '.env', function () use ($data, &$envContent): void {
            $envContent = $this->envWriter->buildEnvContent($data);
            $this->envWriter->writeEnvFile($envContent);
            $this->syncProcessEnvironmentFromEnvContent($envContent);
            $this->applyRuntimePublicPathConfiguration();
        })) {
            return $this->result($steps, $errors);
        }

        if ($applyRuntimeDatabase && ! $this->runCriticalStep($steps, $errors, 'Runtime database', function () use ($data): void {
            $this->artisan('config:clear');
            $this->applyRuntimeDatabaseConfiguration($data);
        })) {
            return $this->result($steps, $errors);
        }

        if ($runMigrations && ! $this->runCriticalStep($steps, $errors, 'Migrations', function (): void {
            if (Schema::hasTable('users') && DB::table('users')->exists()) {
                throw new RuntimeException('Database already contains users. Setup will not reset them. Migrate explicitly and use cms:setup --adopt-existing --force for a verified existing installation.');
            }
            $this->artisan('migrate', ['--force' => true]);
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
            if (! $this->runCriticalStep($steps, $errors, 'Public media', function (): void {
                app(PublicMediaService::class)->prepare();
            })) {
                return $this->result($steps, $errors);
            }
        }

        if ($optimize) {
            $this->runNonCriticalStep($steps, 'Optimization', function (): void {
                $this->artisan('config:cache');
                $this->artisan('route:cache');
                $this->artisan('view:cache');
            });
        }

        if (! $this->runCriticalStep($steps, $errors, 'Content caches', function (): void {
            $this->pageCacheService->flushAll();
            $this->slugResolverService->flushAll();
        })) {
            return $this->result($steps, $errors);
        }

        if ($markInstalled) {
            $this->runCriticalStep($steps, $errors, 'Installed marker', function (): void {
                $this->envWriter->markInstalled();
            });
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
        foreach (Dotenv::parse($envContent) as $key => $value) {
            if (function_exists('putenv')) {
                putenv($key.'='.$value);
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function artisan(string $command, array $arguments = []): void
    {
        $application = app();
        try {
            if (Artisan::call($command, $arguments) !== 0) {
                throw new RuntimeException('Installation command failed: '.$command);
            }
        } finally {
            // config:cache boots another Application. Restore the active CLI/HTTP
            // application so later steps keep its environment/storage/DB scope.
            Container::setInstance($application);
            Facade::setFacadeApplication($application);
            Facade::clearResolvedInstances();
            Model::setConnectionResolver($application['db']);
            Model::setEventDispatcher($application['events']);
        }
    }

    private function applyRuntimePublicPathConfiguration(): void
    {
        $configuredPath = trim((string) ($_ENV['LARAVEL_PUBLIC_PATH'] ?? $_SERVER['LARAVEL_PUBLIC_PATH'] ?? ''));
        $publicPath = $configuredPath !== '' ? $configuredPath : 'html_public';

        app()->usePublicPath($this->absolutePath(base_path(), $publicPath));
    }

    private function absolutePath(string $basePath, string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim($basePath.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
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
