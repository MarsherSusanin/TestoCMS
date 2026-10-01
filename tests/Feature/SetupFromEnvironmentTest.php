<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Setup\Services\SystemCheckService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SetupFromEnvironmentTest extends TestCase
{
    private ?string $marker = null;

    private string $database;

    private array $databaseEnvironment = [];

    private string $environmentDirectory;

    private string $oldEnvironmentPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marker = is_file(storage_path('installed')) ? (string) file_get_contents(storage_path('installed')) : null;
        @unlink(storage_path('installed'));
        $checks = Mockery::mock(SystemCheckService::class);
        $checks->shouldReceive('runAll')->andReturn([]);
        $this->app->instance(SystemCheckService::class, $checks);
        $this->database = storage_path('framework/initialization-'.bin2hex(random_bytes(6)).'.sqlite');
        touch($this->database);
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database] as $key => $value) {
            $this->databaseEnvironment[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->database,
            'app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        DB::purge('sqlite');
        $this->oldEnvironmentPath = $this->app->environmentPath();
        $this->environmentDirectory = storage_path('framework/setup-env-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->environmentDirectory);
        $this->app->useEnvironmentPath($this->environmentDirectory);
        file_put_contents($this->app->environmentFilePath(), 'DB_CONNECTION=sqlite'."\nDB_DATABASE=".$this->database."\nAPP_KEY=".config('app.key')."\nLARAVEL_PUBLIC_PATH=".public_path()."\n");
    }

    protected function tearDown(): void
    {
        if ($this->marker === null) {
            @unlink(storage_path('installed'));
        } else {
            file_put_contents(storage_path('installed'), $this->marker);
        }
        File::delete(base_path('bootstrap/cache/config.php'));
        File::delete(base_path('bootstrap/cache/routes-v7.php'));
        DB::purge('sqlite');
        File::delete($this->database);
        $this->app->useEnvironmentPath($this->oldEnvironmentPath);
        File::deleteDirectory($this->environmentDirectory);
        foreach ($this->databaseEnvironment as $key => [$process, $env, $server]) {
            $process === false ? putenv($key) : putenv($key.'='.$process);
            if ($env === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }
        }
        parent::tearDown();
    }

    public function test_from_env_initializes_and_repeated_run_preserves_admin_and_env(): void
    {
        $envBefore = is_file($this->app->environmentFilePath()) ? hash_file('sha256', $this->app->environmentFilePath()) : null;
        $this->artisan('cms:setup --from-env --no-interaction')->assertSuccessful();
        $this->assertFileExists(storage_path('installed'));
        $admin = User::query()->where('email', 'admin@testocms.local')->firstOrFail();
        $hash = $admin->password;
        $this->assertTrue($admin->hasRole('superadmin'));
        $this->artisan('cms:setup --from-env --no-interaction')->assertSuccessful();
        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertSame(1, User::query()->count());
        $this->assertSame($envBefore, is_file($this->app->environmentFilePath()) ? hash_file('sha256', $this->app->environmentFilePath()) : null);
    }

    public function test_from_env_rejects_missing_key_before_creating_installed_marker(): void
    {
        config(['app.key' => '']);
        $this->artisan('cms:setup --from-env --no-interaction')->assertFailed();
        $this->assertFileDoesNotExist(storage_path('installed'));
    }

    public function test_failed_redo_keeps_installed_marker(): void
    {
        file_put_contents(storage_path('installed'), 'previous installation');
        config(['app.key' => '']);
        $this->artisan('cms:setup --from-env --redo --no-interaction')->assertFailed();
        $this->assertSame('previous installation', file_get_contents(storage_path('installed')));
    }
}
