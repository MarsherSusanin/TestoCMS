<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SetupRedoCrashTest extends TestCase
{
    public function test_fresh_cli_recovers_an_interrupted_redo_without_rerunning_setup(): void
    {
        $directory = sys_get_temp_dir().'/testocms-redo-crash-'.bin2hex(random_bytes(8));
        foreach (['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $path) {
            File::ensureDirectoryExists($directory.'/'.$path);
        }
        $cache = base_path('bootstrap/cache/redo-crash-'.bin2hex(random_bytes(8)).'.php');
        $key = 'base64:'.base64_encode(random_bytes(32));
        $environment = ['APP_ENV' => 'testing', 'APP_KEY' => $key, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database.sqlite', 'LARAVEL_PUBLIC_PATH' => 'html_public', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'CMS_SEED_DEMO_CONTENT' => 'false', 'APP_CONFIG_CACHE' => $cache];
        $content = '';
        foreach ($environment as $name => $value) {
            $content .= $name.'="'.$value.'"'."\n";
        }
        file_put_contents($directory.'/.env', $content);
        touch($directory.'/database.sqlite');
        $script = <<<'PHP_SCRIPT'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->useStoragePath($argv[2]);
$app->useEnvironmentPath($argv[2]);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if ($argv[3] === 'prepare') {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    app(Database\Seeders\RolesAndPermissionsSeeder::class)->run();
    app(App\Modules\Auth\Services\AdminProvisionerService::class)->provision(['name'=>'Original','login'=>'original','email'=>'original@example.test','password'=>'Original123!']);
    app(App\Modules\Setup\Services\EnvWriterService::class)->markInstalled();
    echo hash('sha256', App\Models\User::firstOrFail()->password);
} elseif ($argv[3] === 'interrupt') {
    $service = new class(app(App\Modules\Setup\Services\InstallationIdentityService::class), app(App\Modules\Setup\Services\EnvWriterService::class), app(App\Modules\Updates\Services\UpdateOperationGate::class)) extends App\Modules\Setup\Services\SetupRedoService {
        protected function refreshConfiguration(array $environment): void { exit(73); }
    };
    $service->redo(['app_name'=>'Changed','admin_name'=>'Changed','admin_login'=>'changed','admin_email'=>'changed@example.test','admin_password'=>'Changed123!']);
} else {
    $journal = json_decode(file_get_contents(storage_path('app/private/setup-redo.json')), true, 512, JSON_THROW_ON_ERROR);
    app(App\Modules\Setup\Services\SetupRedoService::class)->recover($journal['operation']);
    echo hash('sha256', App\Models\User::firstOrFail()->password);
}
PHP_SCRIPT;
        try {
            $run = fn ($mode) => new Process([PHP_BINARY, '-r', $script, base_path(), $directory, $mode], base_path(), $environment);
            $prepare = $run('prepare');
            $prepare->mustRun();
            $hash = $prepare->getOutput();
            $before = file_get_contents($directory.'/.env');
            $marker = file_get_contents($directory.'/installed');
            $interrupt = $run('interrupt');
            $interrupt->run();
            $this->assertSame(73, $interrupt->getExitCode(), $interrupt->getErrorOutput());
            $this->assertFileExists($directory.'/framework/down');
            $this->assertFileExists($directory.'/app/private/setup-redo.json');
            $this->assertSame(0600, fileperms($directory.'/app/private/setup-redo.json') & 0777);
            $this->assertNotSame($before, file_get_contents($directory.'/.env'));
            $recovery = $run('recover');
            $recovery->mustRun();
            $this->assertSame($hash, $recovery->getOutput());
            $this->assertSame($before, file_get_contents($directory.'/.env'));
            $this->assertSame($marker, file_get_contents($directory.'/installed'));
            $this->assertFileDoesNotExist($directory.'/framework/down');
            $this->assertFileDoesNotExist($directory.'/app/private/setup-redo.json');
        } finally {
            File::delete($cache);
            File::deleteDirectory($directory);
        }
    }
}
