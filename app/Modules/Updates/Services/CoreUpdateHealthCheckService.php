<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CoreUpdateHealthCheckService
{
    public function runHealthCheck(): void
    {
        DB::connection()->getPdo();
    }

    public function artisanCall(string $command, array $arguments = [], bool $throwOnFailure = true): bool
    {
        if ($command !== 'up') {
            try {
                $all = Artisan::all();
                if (! array_key_exists($command, $all)) {
                    if ($throwOnFailure) {
                        throw new RuntimeException(sprintf('Artisan command "%s" is not available.', $command));
                    }

                    return false;
                }
            } catch (\Throwable $e) {
                if ($throwOnFailure) {
                    throw $e;
                }

                return false;
            }
        }

        $exitCode = Artisan::call($command, $arguments);
        if ($exitCode !== 0 && $throwOnFailure) {
            throw new RuntimeException(sprintf('Artisan command "%s" failed: %s', $command, trim(Artisan::output())));
        }

        return $exitCode === 0;
    }
}
