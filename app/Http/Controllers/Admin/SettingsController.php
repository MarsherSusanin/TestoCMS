<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class SettingsController extends Controller
{
    private const SESSION_KEY = 'admin_ui_locale';
    private const COOKIE_KEY = 'testocms_admin_ui_locale';

    /**
     * @var array<int, string>
     */
    private array $supportedLocales = ['ru', 'en'];

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function edit(Request $request): View
    {
        return view('admin.settings.edit', [
            'currentUiLocale' => $this->resolveCurrentLocale($request),
            'supportedUiLocales' => $this->supportedLocales,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ui_locale' => ['required', 'string', Rule::in($this->supportedLocales)],
        ]);

        $locale = strtolower((string) $validated['ui_locale']);
        $request->session()->put(self::SESSION_KEY, $locale);
        app()->setLocale($locale);

        Cookie::queue(
            Cookie::make(
                self::COOKIE_KEY,
                $locale,
                60 * 24 * 365, // 1 year
                '/',
                null,
                (bool) config('session.secure', false),
                true,
                false,
                SymfonyCookie::SAMESITE_LAX
            )
        );

        $this->auditLogger->log('admin.settings.ui_locale.update', null, [
            'ui_locale' => $locale,
        ], $request);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Язык интерфейса сохранён.');
    }

    private function resolveCurrentLocale(Request $request): string
    {
        $sessionLocale = $request->session()->get(self::SESSION_KEY);
        if (is_string($sessionLocale) && in_array($sessionLocale, $this->supportedLocales, true)) {
            return $sessionLocale;
        }

        $cookieLocale = $request->cookie(self::COOKIE_KEY);
        if (is_string($cookieLocale) && in_array($cookieLocale, $this->supportedLocales, true)) {
            return $cookieLocale;
        }

        $appLocale = (string) app()->getLocale();

        return in_array($appLocale, $this->supportedLocales, true) ? $appLocale : 'ru';
    }
}
