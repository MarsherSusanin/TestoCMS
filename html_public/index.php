<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (! function_exists('testoCmsResolveBasePath')) {
    function testoCmsResolveBasePath(): string
    {
        $candidates = [
            testoCmsConfiguredBasePath(),
            dirname(__DIR__),
            dirname(__DIR__).DIRECTORY_SEPARATOR.'testocms',
            dirname(__DIR__).DIRECTORY_SEPARATOR.'TestoCMS',
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $path = testoCmsNormalizePath($candidate);

            if (is_file($path.'/vendor/autoload.php') && is_file($path.'/bootstrap/app.php')) {
                return $path;
            }
        }

        throw new RuntimeException(
            'Unable to locate the TestoCMS base path. Keep the application directory as ../testocms or update bootstrap_path.php.'
        );
    }
}

if (! function_exists('testoCmsConfiguredBasePath')) {
    function testoCmsConfiguredBasePath(): ?string
    {
        $candidates = [
            $_SERVER['LARAVEL_BASE_PATH'] ?? null,
            $_ENV['LARAVEL_BASE_PATH'] ?? null,
            getenv('LARAVEL_BASE_PATH') ?: null,
        ];

        $bootstrapPathFile = __DIR__.'/bootstrap_path.php';

        if (is_file($bootstrapPathFile)) {
            $candidates[] = require $bootstrapPathFile;
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            return testoCmsNormalizePath($candidate);
        }

        return null;
    }
}

$basePath = testoCmsResolveBasePath();

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once $basePath.'/bootstrap/app.php')
    ->handleRequest(Request::capture());

if (! function_exists('testoCmsNormalizePath')) {
    function testoCmsNormalizePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim(__DIR__.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }
}
