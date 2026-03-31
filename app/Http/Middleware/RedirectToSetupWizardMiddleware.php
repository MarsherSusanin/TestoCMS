<?php

namespace App\Http\Middleware;

use App\Modules\Setup\Services\EnvWriterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToSetupWizardMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        if (EnvWriterService::isInstalled()) {
            if ($request->is('setup*')) {
                abort(404);
            }

            return $next($request);
        }

        // Allow setup routes through
        if ($request->is('setup*')) {
            return $next($request);
        }

        // Allow health check
        if ($request->is('up')) {
            return $next($request);
        }

        // Redirect everything else to wizard
        return redirect()->route('setup.step1');
    }
}
