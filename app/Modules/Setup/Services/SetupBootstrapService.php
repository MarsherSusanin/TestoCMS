<?php

namespace App\Modules\Setup\Services;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SetupBootstrapService
{
    /** Configure first-run HTTP without resolving sessions, encryption or a database. */
    public function configure(): void
    {
        // config:cache boots a ConsoleKernel even when invoked by an HTTP
        // installer. Its fresh configuration must retain the real env drivers.
        if (app()->runningInConsole() || app()->resolved(ConsoleKernel::class) || EnvWriterService::isInstalled()) {
            return;
        }

        $key = (string) config('app.key', '');
        if ($key === '') {
            $key = $this->bootstrapKey();
        }
        $sessions = storage_path('framework/setup-sessions');
        File::ensureDirectoryExists($sessions, 0700);
        config([
            'app.key' => $key,
            'session.driver' => 'file',
            'session.files' => $sessions,
            'session.cookie' => 'testocms_setup_'.substr(hash('sha256', base_path()), 0, 12),
            'session.encrypt' => true,
            'session.secure' => null,
            'session.same_site' => 'lax',
            'session.domain' => null,
            'session.path' => '/',
            'cache.default' => 'file',
            'cache.stores.file.path' => storage_path('framework/cache/data'),
            'cache.stores.file.lock_path' => storage_path('framework/cache/data'),
        ]);
    }

    private function bootstrapKey(): string
    {
        $directory = storage_path('app/private');
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/setup.key';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Cannot create installer encryption key.');
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock installer encryption key.');
            }
            $key = trim((string) stream_get_contents($handle));
            if ($key === '') {
                $key = 'base64:'.base64_encode(random_bytes(32));
                if (fwrite($handle, $key) === false || ! fflush($handle)) {
                    throw new RuntimeException('Cannot save installer encryption key.');
                }
            }
            chmod($path, 0600);

            return $key;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
