<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;

class CoreUpdateHealthCheckService
{
    public function runHealthCheck(): array
    {
        DB::connection()->getPdo();

        return $this->assertApplicationBoots();
    }

    /**
     * Verify the freshly-applied code actually boots by issuing an out-of-band
     * HTTP request against the live site. This runs in a brand-new PHP process
     * via the web server, so a broken autoloader / fatal after a vendor swap is
     * detected (HTTP 5xx) and surfaces as a failure that triggers auto-rollback
     * — unlike the previous DB-only ping, which a fatal site passes.
     *
     * If the site cannot be reached at all (e.g. loopback blocked on some
     * shared hosts) the check is treated as unverifiable rather than failed, so
     * a healthy update is not rolled back on infrastructure quirks; the
     * connection error is reported for the operator.
     */
    private function assertApplicationBoots(): array
    {
        $url = $this->resolveHealthUrl();
        if ($url === null) {
            return ['status' => 'health_unverified', 'warning' => 'No HTTP health URL is configured.'];
        }

        try {
            $response = Http::timeout((int) config('updates.health_check_timeout', 10))
                ->withoutRedirecting()
                ->withHeaders(['X-CMS-Health-Check' => '1'])
                ->get($url);
        } catch (\Throwable $e) {
            // Strict mode: an unreachable site counts as a failed check (and
            // triggers rollback) instead of "unverifiable". Off by default so
            // hosts with blocked loopback don't roll back healthy updates.
            if ((bool) config('updates.health_check_strict', false)) {
                throw new RuntimeException(sprintf(
                    'Post-update health check failed: %s is unreachable (%s).',
                    $url,
                    $e->getMessage()
                ), 0, $e);
            }

            report($e);

            return ['status' => 'health_unverified', 'warning' => 'Health endpoint is unreachable: '.$url];
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Post-update health check failed: %s returned HTTP %d.',
                $url,
                $response->status()
            ));
        }

        return ['status' => 'verified', 'warning' => null];
    }

    private function resolveHealthUrl(): ?string
    {
        // An explicitly configured URL wins even under testing, so the
        // failed-health-check → rollback path is exercisable with Http::fake.
        $explicit = trim((string) config('updates.health_check_url', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        if (app()->environment('testing')) {
            return null;
        }

        $base = trim((string) config('app.url', ''));
        if ($base === '' || ! str_starts_with($base, 'http')) {
            return null;
        }

        $path = '/'.ltrim((string) config('updates.health_check_path', '/up'), '/');

        return rtrim($base, '/').$path;
    }

    public function artisanCall(string $command, array $arguments = [], bool $throwOnFailure = true): bool
    {
        // New files/vendor must be loaded by a fresh kernel, not this request.
        if (! app()->runningUnitTests() && ! in_array($command, ['down', 'up'], true)) {
            $processArguments = [PHP_BINARY, base_path('artisan'), $command, '--no-interaction'];
            foreach ($arguments as $key => $value) {
                if ($value === true) {
                    $processArguments[] = (string) $key;
                } elseif ($value !== false && $value !== null) {
                    $processArguments[] = (string) $key.'='.(string) $value;
                }
            }
            try {
                $process = new Process($processArguments, base_path());
                $process->setTimeout(300);
                $process->run();
                if (! $process->isSuccessful() && $throwOnFailure) {
                    throw new RuntimeException('Fresh-kernel command failed: '.$command.' '.trim($process->getErrorOutput()."\n".$process->getOutput()));
                }

                return $process->isSuccessful();
            } catch (\Throwable $e) {
                if ($throwOnFailure) {
                    throw $e;
                }

                return false;
            }
        }
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

    public function refreshPublicRuntime(): void
    {
        // This bootstrap also works after rollback to a version without the new commands.
        $script = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (class_exists(\App\Modules\Setup\Services\PublicMediaService::class)) {
    $app->make(\App\Modules\Setup\Services\PublicMediaService::class)->prepare();
} else {
    \Illuminate\Support\Facades\Artisan::call('storage:link');
}
$app->make(\App\Modules\Extensibility\Services\ModulePublicAssetsPublisherService::class)->republishInstalledModules();
exit(\Illuminate\Support\Facades\Artisan::call('cms:modules:cache'));
PHP;
        $process = new Process([PHP_BINARY, '-r', $script], base_path());
        $process->setTimeout(300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Fresh-kernel public runtime refresh failed: '.$process->getErrorOutput());
        }
    }
}
