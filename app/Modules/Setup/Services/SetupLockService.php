<?php

namespace App\Modules\Setup\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class SetupLockService
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        File::ensureDirectoryExists(storage_path('app/private'), 0700);
        $handle = fopen(storage_path('app/private/setup.lock'), 'c');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Another installation is already running.');
        }
        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
