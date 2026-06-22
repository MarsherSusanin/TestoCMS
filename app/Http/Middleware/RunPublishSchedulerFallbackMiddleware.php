<?php

namespace App\Http\Middleware;

use App\Modules\Ops\Services\PublishSchedulerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RunPublishSchedulerFallbackMiddleware
{
    public function __construct(private readonly PublishSchedulerService $publishSchedulerService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $lastRun = (int) Cache::get('cms:scheduler:last-run', 0);

        if ((now()->timestamp - $lastRun) > 30) {
            // Acquire an atomic lock so concurrent requests don't all pass the
            // check-then-act window and run the scheduler (and flush caches)
            // more than once. Non-blocking: if another request holds it, skip.
            Cache::lock('cms:scheduler:fallback-lock', 30)->get(function (): void {
                $lastRun = (int) Cache::get('cms:scheduler:last-run', 0);
                if ((now()->timestamp - $lastRun) > 30) {
                    $this->publishSchedulerService->runDue();
                    Cache::put('cms:scheduler:last-run', now()->timestamp, 120);
                }
            });
        }

        return $next($request);
    }
}
