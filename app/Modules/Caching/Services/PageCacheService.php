<?php

namespace App\Modules\Caching\Services;

use App\Modules\SEO\Services\SeoCacheKeys;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PageCacheService
{
    private const KEY_LIST = 'cms:page-cache:keys';

    public function keyFromRequest(Request $request): string
    {
        $locale = app()->getLocale();

        return 'cms:page-cache:'.$locale.':'.sha1($request->fullUrl());
    }

    public function get(Request $request): ?Response
    {
        $payload = Cache::get($this->keyFromRequest($request));

        if (! is_array($payload)) {
            return null;
        }

        return new Response(
            $payload['content'] ?? '',
            $payload['status'] ?? 200,
            $payload['headers'] ?? []
        );
    }

    public function put(Request $request, Response $response): void
    {
        if ($response->getStatusCode() !== 200) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $key = $this->keyFromRequest($request);

        // Preserve SEO-relevant headers: a noindex page must stay noindex
        // when served from cache, not fall back to crawlable defaults.
        $headers = [
            'Content-Type' => $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
        ];
        if ($response->headers->has('X-Robots-Tag')) {
            $headers['X-Robots-Tag'] = (string) $response->headers->get('X-Robots-Tag');
        }

        Cache::put($key, [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $headers,
        ], config('cms.full_page_cache_ttl', 300));

        $keys = Cache::get(self::KEY_LIST, []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::forever(self::KEY_LIST, $keys);
        }
    }

    public function flushAll(): void
    {
        $keys = Cache::get(self::KEY_LIST, []);

        foreach ($keys as $key) {
            Cache::forget((string) $key);
        }

        Cache::forget(self::KEY_LIST);

        // Every flushAll() call means "public content changed", so the cached
        // SEO endpoints (sitemaps, llms.txt) are stale too — without this a
        // just-published page can stay out of the sitemap for the full TTL.
        foreach (SeoCacheKeys::all() as $seoKey) {
            Cache::forget($seoKey);
        }
    }
}
