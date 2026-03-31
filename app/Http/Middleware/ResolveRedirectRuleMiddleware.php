<?php

namespace App\Http\Middleware;

use App\Models\RedirectRule;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ResolveRedirectRuleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('redirect_rules')) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');

        $rule = RedirectRule::query()
            ->where('from_path', $path)
            ->where('is_active', true)
            ->first();

        if ($rule !== null) {
            return new RedirectResponse($rule->to_path, $rule->http_code);
        }

        return $next($request);
    }
}
