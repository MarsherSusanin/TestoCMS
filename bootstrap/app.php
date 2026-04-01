<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$basePath = dirname(__DIR__);
$argv = $_SERVER['argv'] ?? [];
$isTestingCommand = PHP_SAPI === 'cli' && in_array('test', is_array($argv) ? $argv : [], true);

if (($_ENV['APP_ENV'] ?? null) === 'testing' || $isTestingCommand) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

if (! defined('TESTOCMS_BOOTSTRAP_IGNORE_DOTENV_PUBLIC_PATH')) {
    define('TESTOCMS_BOOTSTRAP_IGNORE_DOTENV_PUBLIC_PATH', $isTestingCommand || (($_ENV['APP_ENV'] ?? null) === 'testing'));
}

$app = Application::configure(basePath: $basePath)
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

if (! function_exists('testoCmsBootstrapEnvValue')) {
    function testoCmsBootstrapEnvValue(string $basePath, string $key): ?string
    {
        $candidates = [
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
            getenv($key) ?: null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        if (defined('TESTOCMS_BOOTSTRAP_IGNORE_DOTENV_PUBLIC_PATH') && TESTOCMS_BOOTSTRAP_IGNORE_DOTENV_PUBLIC_PATH) {
            return null;
        }

        $envPath = $basePath.'/.env';

        if (! is_file($envPath)) {
            return null;
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separatorPosition = strpos($line, '=');

            if ($separatorPosition === false) {
                continue;
            }

            $candidateKey = trim(substr($line, 0, $separatorPosition));

            if ($candidateKey !== $key) {
                continue;
            }

            $candidateValue = trim(substr($line, $separatorPosition + 1));

            if (
                strlen($candidateValue) >= 2
                && (($candidateValue[0] === '"' && str_ends_with($candidateValue, '"')) || ($candidateValue[0] === '\'' && str_ends_with($candidateValue, '\'')))
            ) {
                $candidateValue = substr($candidateValue, 1, -1);
            }

            return $candidateValue !== '' ? $candidateValue : null;
        }

        return null;
    }
}

if (! function_exists('testoCmsBootstrapPublicPath')) {
    function testoCmsBootstrapPublicPath(string $basePath): string
    {
        if (defined('TESTOCMS_BOOTSTRAP_IGNORE_DOTENV_PUBLIC_PATH') && TESTOCMS_BOOTSTRAP_IGNORE_DOTENV_PUBLIC_PATH) {
            return testoCmsBootstrapAbsolutePath($basePath, 'html_public');
        }

        $configuredPath = testoCmsBootstrapEnvValue($basePath, 'LARAVEL_PUBLIC_PATH');
        $path = $configuredPath !== null && $configuredPath !== ''
            ? $configuredPath
            : 'html_public';

        return testoCmsBootstrapAbsolutePath($basePath, $path);
    }
}

if (! function_exists('testoCmsBootstrapAbsolutePath')) {
    function testoCmsBootstrapAbsolutePath(string $basePath, string $path): string
    {
        if (testoCmsBootstrapIsAbsolutePath($path)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim($basePath.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }
}

if (! function_exists('testoCmsBootstrapIsAbsolutePath')) {
    function testoCmsBootstrapIsAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}

$app->usePublicPath(testoCmsBootstrapPublicPath($basePath));

return $app;
