<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$argv = $_SERVER['argv'] ?? [];
$isTestingCommand = PHP_SAPI === 'cli' && in_array('test', is_array($argv) ? $argv : [], true);

if (($_ENV['APP_ENV'] ?? null) === 'testing' || $isTestingCommand) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            \App\Http\Middleware\RedirectToSetupWizardMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureLocalBaselineMiddleware::class,
            \App\Http\Middleware\SetLocaleFromRoute::class,
            \App\Http\Middleware\SetAdminInterfaceLocale::class,
            \App\Http\Middleware\ResolveRedirectRuleMiddleware::class,
            \App\Http\Middleware\RunPublishSchedulerFallbackMiddleware::class,
            \App\Http\Middleware\FullPageCacheMiddleware::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'content_api_key' => \App\Http\Middleware\ValidateContentApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
