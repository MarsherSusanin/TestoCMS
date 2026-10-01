<?php

namespace App\Http\Middleware;

use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Caching\Services\PublicContentVersionService;
use App\Modules\Setup\Services\EnvWriterService;
use App\Modules\Web\Services\PublicVisibilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FullPageCacheMiddleware
{
    public function __construct(private readonly PageCacheService $cache, private readonly PublicContentVersionService $versions, private readonly PublicVisibilityService $visibility) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ((! app()->runningUnitTests() && ! EnvWriterService::isInstalled())
            || ! $this->shouldUseCache($request) || ! $this->versions->available()) {
            return $next($request);
        }
        // Capture before ALL content reads so an old renderer cannot publish
        // its result into the generation of a later committed mutation.
        $this->cache->captureVersion($request);
        $this->visibility->assertRequestIsLive($request);
        $cached = $this->cache->get($request);
        if ($cached !== null && $this->cache->versionIsCurrent($request)) {
            $cached->headers->set('X-TestoCMS-Cache', 'HIT');

            return $cached;
        }
        if (! $this->cache->versionIsCurrent($request)) {
            $this->cache->captureVersion($request);
            $this->visibility->assertRequestIsLive($request);
        }
        $response = $next($request);
        if ($response instanceof \Illuminate\Http\Response) {
            $this->cache->put($request, $response);
            $response->headers->set('X-TestoCMS-Cache', 'MISS');
        }

        return $response;
    }

    private function shouldUseCache(Request $request): bool
    {
        return $request->isMethod('GET') && $request->user() === null
            && ! $request->is('admin*', 'api*', 'setup*', 'storage*', 'up', 'healthz', 'preview*');
    }
}
