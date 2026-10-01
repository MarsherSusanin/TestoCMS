<?php

namespace App\Http\Middleware;

use App\Modules\Updates\Services\UpdateOperationGate;
use Closure;
use Illuminate\Http\Request;

class CoordinateCoreUpdates
{
    public function handle(Request $request, Closure $next): mixed
    {
        // The updater obtains the exclusive lock itself; health runs out of band.
        if ($request->is('up', 'healthz', 'admin/updates/apply', 'admin/updates/rollback/*')) {
            return $next($request);
        }
        $gate = app(UpdateOperationGate::class);
        if (! $gate->acquire()) {
            return response('CMS update in progress.', 503, ['Retry-After' => '60', 'Cache-Control' => 'no-store']);
        }
        try {
            return $next($request);
        } finally {
            $gate->release();
        }
    }
}
