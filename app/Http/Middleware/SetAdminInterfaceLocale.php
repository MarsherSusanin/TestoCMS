<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminInterfaceLocale
{
    private const SESSION_KEY = 'admin_ui_locale';
    private const COOKIE_KEY = 'testocms_admin_ui_locale';

    /**
     * @var array<int, string>
     */
    private array $supported = ['ru', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            $locale = $this->resolveLocale($request);
            if ($locale !== null) {
                app()->setLocale($locale);
            }
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): ?string
    {
        $sessionLocale = $request->session()->get(self::SESSION_KEY);
        if (is_string($sessionLocale)) {
            $normalized = $this->normalizeLocale($sessionLocale);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $cookieLocale = $request->cookie(self::COOKIE_KEY);
        if (is_string($cookieLocale)) {
            $normalized = $this->normalizeLocale($cookieLocale);
            if ($normalized !== null) {
                $request->session()->put(self::SESSION_KEY, $normalized);

                return $normalized;
            }
        }

        return null;
    }

    private function normalizeLocale(string $locale): ?string
    {
        $normalized = strtolower(trim($locale));

        return in_array($normalized, $this->supported, true) ? $normalized : null;
    }
}

