<?php

namespace App\Http\Middleware;

use App\Modules\I18n\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRoute
{
    public function __construct(private readonly LocaleResolver $localeResolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeLocale = $request->route('locale');
        $locale = $this->localeResolver->normalize(is_string($routeLocale) ? $routeLocale : null);

        app()->setLocale($locale);

        return $next($request);
    }
}
