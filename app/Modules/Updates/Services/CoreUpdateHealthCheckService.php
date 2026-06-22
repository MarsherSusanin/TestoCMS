<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CoreUpdateHealthCheckService
{
    public function runHealthCheck(): void
    {
        DB::connection()->getPdo();
        $this->assertApplicationBoots();
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
    private function assertApplicationBoots(): void
    {
        $url = $this->resolveHealthUrl();
        if ($url === null) {
            return;
        }

        try {
            $response = Http::timeout((int) config('updates.health_check_timeout', 10))
                ->withHeaders(['X-CMS-Health-Check' => '1'])
                ->get($url);
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        if ($response->serverError()) {
            throw new RuntimeException(sprintf(
                'Post-update health check failed: %s returned HTTP %d.',
                $url,
                $response->status()
            ));
        }
    }

    private function resolveHealthUrl(): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }

        $explicit = trim((string) config('updates.health_check_url', ''));
        if ($explicit !== '') {
            return $explicit;
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
