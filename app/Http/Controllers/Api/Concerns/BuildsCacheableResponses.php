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
    protected function cacheableJson(Request $request, array $payload, ?CarbonInterface $lastModified = null, int $maxAge = 0): JsonResponse
    {
        $response = response()->json($payload);
        $etag = '"'.sha1((string) $response->getContent()).'"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', sprintf('public, max-age=%d, must-revalidate', $maxAge));
        if ($lastModified !== null) {
            $response->headers->set('Last-Modified', gmdate(DATE_RFC7231, $lastModified->getTimestamp()));
        }

        // Header presence takes precedence, including empty or malformed values.
        if ($request->headers->has('If-None-Match')) {
            $value = trim((string) $request->headers->get('If-None-Match'));
            $matches = $value === '*';
            if (! $matches && preg_match('/^(?:W\/)?"[^"\x00-\x20\x7f]*"(?:\s*,\s*(?:W\/)?"[^"\x00-\x20\x7f]*")*$/D', $value) === 1) {
                preg_match_all('/(?:W\/)?("[^"\x00-\x20\x7f]*")/', $value, $tags);
                $matches = in_array($etag, $tags[1], true);
            }
            if ($matches) {
                $response->setStatusCode(304);
                $response->setContent('');
            }
        } elseif ($lastModified !== null) {
            $date = $request->headers->get('If-Modified-Since');
            $timestamp = $date === null ? false : strtotime($date);
            if ($timestamp !== false && $timestamp >= $lastModified->getTimestamp()) {
                $response->setStatusCode(304);
                $response->setContent('');
            }
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
