<?php

namespace App\Http\Controllers\Api\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait BuildsCacheableResponses
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function cacheableJson(Request $request, array $payload, ?CarbonInterface $lastModified = null, int $maxAge = 120): JsonResponse
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $etag = '"'.sha1($encoded).'"';

        $ifNoneMatch = (string) $request->headers->get('If-None-Match', '');
        if ($ifNoneMatch !== '' && str_contains($ifNoneMatch, $etag)) {
            return response()->json(null, 304, [
                'ETag' => $etag,
                'Cache-Control' => sprintf('public, max-age=%d', $maxAge),
            ]);
        }

        if ($lastModified !== null) {
            $ifModifiedSince = $request->headers->get('If-Modified-Since');
            if ($ifModifiedSince !== null && strtotime($ifModifiedSince) >= $lastModified->getTimestamp()) {
                return response()->json(null, 304, [
                    'ETag' => $etag,
                    'Last-Modified' => gmdate(DATE_RFC7231, $lastModified->getTimestamp()),
                    'Cache-Control' => sprintf('public, max-age=%d', $maxAge),
                ]);
            }
        }

        $response = response()->json($payload);
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', sprintf('public, max-age=%d', $maxAge));

        if ($lastModified !== null) {
            $response->headers->set('Last-Modified', gmdate(DATE_RFC7231, $lastModified->getTimestamp()));
        }

        return $response;
    }

    protected function resolveLocaleFromRequest(Request $request): string
    {
        $locale = strtolower((string) $request->query('locale', app()->getLocale()));
        $supported = config('cms.supported_locales', ['en']);

        return in_array($locale, $supported, true)
            ? $locale
            : (string) config('cms.default_locale', 'en');
    }
}
