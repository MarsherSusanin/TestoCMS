<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SetupColdStartTest extends TestCase
{
    public function test_console_kernel_with_http_console_flag_preserves_persistent_drivers_before_install(): void
    {
        $storage = sys_get_temp_dir().'/testocms-console-cold-start-'.bin2hex(random_bytes(8));
        foreach (['app/private', 'framework/sessions', 'framework/views', 'framework/cache', 'logs'] as $path) {
            File::ensureDirectoryExists($storage.'/'.$path);
        }
        $script = <<<'PHP_SCRIPT'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->useStoragePath($argv[2]);
        $app->useEnvironmentPath($argv[2]);
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        echo json_encode([
            'running_in_console' => $app->runningInConsole(),
            'console_kernel_resolved' => $app->resolved(Illuminate\Contracts\Console\Kernel::class),
            'session' => config('session.driver'),
            'cache' => config('cache.default'),
            'key' => (string) config('app.key'),
        ]);
        PHP_SCRIPT;
        try {
            $process = new Process([PHP_BINARY, '-r', $script, base_path(), $storage], base_path(), [
                'APP_ENV' => 'production', 'APP_RUNNING_IN_CONSOLE' => 'false', 'APP_KEY' => '',
                'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $storage.'/uncreated.sqlite',
                'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database',
                'APP_CONFIG_CACHE' => $storage.'/config.php', 'APP_ROUTES_CACHE' => $storage.'/routes.php',
                'APP_LOG_LEVEL' => 'error',
            ]);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true);
            $this->assertFalse($result['running_in_console']);
            $this->assertTrue($result['console_kernel_resolved']);
            $this->assertSame('database', $result['session']);
            $this->assertSame('database', $result['cache']);
            $this->assertSame('', $result['key']);
            $this->assertFileDoesNotExist($storage.'/.env');
            $this->assertFileDoesNotExist($storage.'/installed');
            $this->assertFileDoesNotExist($storage.'/app/private/setup.key');
            $this->assertDirectoryDoesNotExist($storage.'/framework/setup-sessions');
            $this->assertFileDoesNotExist($storage.'/uncreated.sqlite');
        } finally {
            File::deleteDirectory($storage);
        }
    }

    public function test_real_production_bootstrap_serves_wizard_without_env_key_or_database_tables(): void
    {
        $storage = sys_get_temp_dir().'/testocms-cold-start-'.bin2hex(random_bytes(8));
        foreach (['app/private', 'framework/sessions', 'framework/views', 'framework/cache', 'logs'] as $path) {
            File::ensureDirectoryExists($storage.'/'.$path);
        }
        $script = <<<'PHP_SCRIPT'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->useStoragePath($argv[2]);
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $statuses = [];
        foreach (['/setup/step/1', '/setup/step/2'] as $path) {
            $request = Illuminate\Http\Request::create($path, 'GET');
            $response = $kernel->handle($request);
            $statuses[] = $response->getStatusCode();
            $kernel->terminate($request, $response);
        }
        echo json_encode(['statuses' => $statuses, 'session' => config('session.driver'), 'key' => strlen((string) config('app.key')) > 0]);
        PHP_SCRIPT;
        try {
            $process = new Process([PHP_BINARY, '-r', $script, base_path(), $storage], base_path(), [
                'APP_ENV' => 'production', 'APP_RUNNING_IN_CONSOLE' => 'false', 'APP_KEY' => '',
                'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $storage.'/uncreated.sqlite',
                'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database',
                'APP_CONFIG_CACHE' => $storage.'/config.php', 'APP_ROUTES_CACHE' => $storage.'/routes.php',
                'APP_LOG_LEVEL' => 'error',
            ]);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true);
            $this->assertSame([200, 200], $result['statuses'] ?? null);
            $this->assertSame('file', $result['session']);
            $this->assertTrue($result['key']);
            $this->assertFileExists($storage.'/app/private/setup.key');
            $this->assertFileDoesNotExist($storage.'/uncreated.sqlite');
        } finally {
            File::deleteDirectory($storage);
        }
    }
}
