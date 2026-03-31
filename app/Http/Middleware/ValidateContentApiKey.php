<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ValidateContentApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $requiredKey = (string) config('cms.content_api.key', '');
        $providedHeaderOrQueryKey = (string) ($request->header('X-API-Key') ?? $request->query('key', ''));
        $providedBearerKey = trim((string) ($request->bearerToken() ?? ''));
        $providedCandidates = array_values(array_unique(array_filter([
            trim($providedHeaderOrQueryKey),
            $providedBearerKey,
        ])));

        $authorized = false;
        $bucketSubject = null;

        if ($providedHeaderOrQueryKey !== '' && $requiredKey !== '' && hash_equals($requiredKey, $providedHeaderOrQueryKey)) {
            $authorized = true;
            $bucketSubject = 'static:'.$providedHeaderOrQueryKey;
        }

        if (! $authorized) {
            $managedToken = $this->resolveManagedToken($providedCandidates);
            if ($managedToken !== null) {
                $authorized = true;
                $bucketSubject = 'pat:'.$managedToken->id;
            }
        }

        if (! $authorized) {
            if ($requiredKey !== '' || $providedCandidates !== []) {
                abort(401, 'Invalid API key.');
            }

            // Backward compatibility: if static key is not configured, keep open access.
            $bucketSubject = 'ip:'.$request->ip();
        }

        $bucket = 'content-api:'.$bucketSubject;
        $maxAttempts = (int) config('cms.content_api.rate_limit_per_minute', 120);

        if (RateLimiter::tooManyAttempts($bucket, $maxAttempts)) {
            abort(429, 'Too many requests.');
        }

        RateLimiter::hit($bucket, 60);

        return $next($request);
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function resolveManagedToken(array $candidates): ?PersonalAccessToken
    {
        foreach ($candidates as $candidate) {
            $token = PersonalAccessToken::findToken($candidate);
            if (! $token instanceof PersonalAccessToken) {
                continue;
            }
            if (! $token->can('content:read')) {
                continue;
            }
            if ($token->expires_at !== null && $token->expires_at->isPast()) {
                continue;
            }

            return $token;
        }

        return null;
    }
}
