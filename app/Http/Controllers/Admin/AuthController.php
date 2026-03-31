<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\LocalBaselineBootstrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private readonly LocalBaselineBootstrapService $localBaselineBootstrap,
    ) {
    }

    public function showLogin()
    {
        $this->localBaselineBootstrap->ensureBaseline();

        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $this->localBaselineBootstrap->ensureBaseline();

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            $isEn = str_starts_with((string) app()->getLocale(), 'en');
            return back()->withErrors([
                'email' => $isEn ? 'Invalid email or password.' : 'Неверный email или пароль.',
            ])->onlyInput('email');
        }

        $user = $request->user();
        if ($user !== null && strtolower((string) $user->status) !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $isEn = str_starts_with((string) app()->getLocale(), 'en');

            return back()->withErrors([
                'email' => $isEn ? 'Account is blocked.' : 'Учетная запись заблокирована.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        if ($user !== null) {
            $user->last_login_at = now();
            $user->save();
        }

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
