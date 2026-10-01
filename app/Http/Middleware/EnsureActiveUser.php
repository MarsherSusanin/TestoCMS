<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public const SESSION_VERSION = 'cms_auth_version';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $current = User::query()->find($user->id);
        $blocked = ! $current || $current->status !== 'active';
        $stale = false;
        if ($request->hasSession() && $current !== null) {
            $guard = Auth::guard('web');
            // Persisted browser sessions carry a version; setUser() callers do
            // not create a browser login, but still undergo the active check.
            if ($guard instanceof SessionGuard && $request->session()->has($guard->getName())) {
                if (! $blocked && $guard->viaRemember()) {
                    // A credential change may occur between cookie validation
                    // and this middleware. Never promote that stale recall to
                    // the new authentication version.
                    $rememberToken = (string) $user->getRememberToken();
                    if ($rememberToken !== '' && hash_equals((string) $current->getRememberToken(), $rememberToken)) {
                        $request->session()->put(self::SESSION_VERSION, (int) $current->auth_version);
                    }
                }
                $stale = (int) $request->session()->get(self::SESSION_VERSION, -1) !== (int) $current->auth_version;
            }
        }

        if ($blocked || $stale) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $blocked ? 'Account is blocked.' : 'Authentication has expired.'], $blocked ? 403 : 401);
            }

            return redirect()->route('admin.login')->withErrors(['email' => 'Доступ отозван. Войдите повторно.']);
        }

        return $next($request);
    }
}
