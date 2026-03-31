<?php

namespace App\Http\Middleware;

use App\Modules\Core\Services\LocalBaselineBootstrapService;
use App\Modules\Setup\Services\EnvWriterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalBaselineMiddleware
{
    public function __construct(
        private readonly LocalBaselineBootstrapService $localBaselineBootstrap,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldEnsureBaseline($request)) {
            $this->localBaselineBootstrap->ensureBaseline();
        }

        return $next($request);
    }

    private function shouldEnsureBaseline(Request $request): bool
    {
        if (! EnvWriterService::isInstalled()) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if (
            $request->is('admin*')
            || $request->is('api*')
            || $request->is('setup*')
            || $request->is('up')
        ) {
            return false;
        }

        return true;
    }
}
