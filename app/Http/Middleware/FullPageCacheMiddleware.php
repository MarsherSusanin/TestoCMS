<?php

namespace App\Http\Middleware;

use App\Modules\Caching\Services\PageCacheService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FullPageCacheMiddleware
{
    public function __construct(private readonly PageCacheService $pageCacheService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldUseCache($request)) {
            return $next($request);
        }

        $cached = $this->pageCacheService->get($request);
        if ($cached !== null) {
            $cached->headers->set('X-TestoCMS-Cache', 'HIT');

            return $cached;
        }

        $response = $next($request);

        if ($response instanceof \Illuminate\Http\Response) {
            $this->pageCacheService->put($request, $response);
            $response->headers->set('X-TestoCMS-Cache', 'MISS');
        }

        return $response;
    }

    private function shouldUseCache(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->user() !== null) {
            return false;
        }

        if ($request->is('admin*') || $request->is('api*') || $request->is('setup*') || $request->is('up')) {
            return false;
        }

        return true;
    }
}
