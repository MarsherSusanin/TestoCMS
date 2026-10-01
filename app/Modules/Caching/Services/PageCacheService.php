<?php

namespace App\Modules\Caching\Services;

use App\Modules\SEO\Services\SeoCacheKeys;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PageCacheService
{
    public function __construct(private readonly PublicContentVersionService $versions) {}

    public function captureVersion(Request $request): string
    {
        $version = $this->versions->current();
        $request->attributes->set('cms.content_version', $version);

        return $version;
    }

    public function versionIsCurrent(Request $request): bool
    {
        return $request->attributes->get('cms.content_version') === $this->versions->current();
    }

    public function keyFromRequest(Request $request): string
    {
        $version = $request->attributes->get('cms.content_version');
        if ($version === null) {
            $version = $this->captureVersion($request);
        }

        return 'cms:page-cache:'.PublicContentVersionService::CACHE_SCHEMA.':g'.$version.':'.app()->getLocale().':'.sha1($request->fullUrl());
    }

    public function get(Request $request): ?Response
    {
        $payload = Cache::get($this->keyFromRequest($request));
        if (! is_array($payload)) {
            return null;
        }

        return new Response($payload['content'] ?? '', $payload['status'] ?? 200, $payload['headers'] ?? []);
    }

    public function put(Request $request, Response $response): void
    {
        if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')
            || str_contains((string) $response->headers->get('Cache-Control', ''), 'no-store') || ! $this->versionIsCurrent($request)) {
            return;
        }
        $headers = ['Content-Type' => $response->headers->get('Content-Type', 'text/html; charset=UTF-8')];
        if ($response->headers->has('X-Robots-Tag')) {
            $headers['X-Robots-Tag'] = (string) $response->headers->get('X-Robots-Tag');
        }
        // A concurrent writer after this check can only leave data under the old
        // captured generation. No reader of the committed generation can reuse it.
        Cache::put($this->keyFromRequest($request), ['content' => $response->getContent(), 'status' => 200, 'headers' => $headers], config('cms.full_page_cache_ttl', 300));
    }

    public function flushAll(bool $advanceVersion = true): void
    {
        if ($advanceVersion) {
            $this->versions->bump();
        }
        // Clean legacy unversioned entries too during upgrades. Generations make
        // correctness independent of this best-effort eviction and its races.
        foreach ((array) Cache::get('cms:page-cache:keys', []) as $key) {
            Cache::forget((string) $key);
        }
        Cache::forget('cms:page-cache:keys');
        foreach (SeoCacheKeys::all() as $key) {
            Cache::forget($key);
        }
    }
}
