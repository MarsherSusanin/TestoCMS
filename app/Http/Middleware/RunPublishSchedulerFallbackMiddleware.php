<?php

namespace App\Http\Middleware;

use App\Modules\Ops\Services\PublishSchedulerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RunPublishSchedulerFallbackMiddleware
{
    public function __construct(private readonly PublishSchedulerService $publishSchedulerService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $lastRun = (int) Cache::get('cms:scheduler:last-run', 0);
        $now = now()->timestamp;

        if (($now - $lastRun) > 30) {
            $this->publishSchedulerService->runDue();
            Cache::put('cms:scheduler:last-run', $now, 120);
        }

        return $next($request);
    }
}
