<?php

return [
    'current_version' => env('CMS_VERSION', '1.0.0'),

    'package_artifact' => env('CMS_UPDATE_PACKAGE_ARTIFACT', 'core-updater'),

    'default_channel' => env('CMS_UPDATE_CHANNEL', 'stable'),

    'mode' => env('CMS_UPDATE_MODE', 'auto'), // auto|filesystem-updater|deploy-hook

    'server_url' => env('CMS_UPDATE_SERVER_URL', ''),

    'public_key' => env('CMS_UPDATE_PUBLIC_KEY', ''),

    'deploy_hook_url' => env('CMS_UPDATE_DEPLOY_HOOK_URL', ''),

    'deploy_hook_token' => env('CMS_UPDATE_DEPLOY_HOOK_TOKEN', ''),

    'max_zip_size_mb' => (int) env('CMS_UPDATE_MAX_ZIP_MB', 120),

    'backup_retention' => (int) env('CMS_UPDATE_BACKUP_RETENTION', 5),

    'storage_root' => storage_path('app/private/core_updates'),

    'base_path' => env('CMS_UPDATE_BASE_PATH', base_path()),

    'allowlist_paths' => [
        'app',
        'bootstrap',
        'bundled-modules',
        'config',
        'database',
        'html_public',
        'lang',
        'public',
        'resources',
        'routes',
        'artisan',
        'composer.json',
        'composer.lock',
        'vendor',
    ],

    'managed_public_paths' => [
        '.htaccess',
        'bootstrap_path.php',
        'index.php',
        'favicon.ico',
        'favicon.svg',
        'robots.txt',
        'brand',
    ],

    'forbidden_paths' => [
        '.env',
        'storage',
        'modules',
        'modules-dev',
    ],

    'manifest_endpoint' => '/api/updates/manifest',
    'package_endpoint_template' => '/api/updates/packages/{version}.zip',
    'signature_endpoint_template' => '/api/updates/packages/{version}.sig',

    'http_timeout' => (int) env('CMS_UPDATE_HTTP_TIMEOUT', 15),

    'health_check_path' => env('CMS_UPDATE_HEALTH_PATH', '/up'),

    // Optional absolute URL used by the post-apply health check to verify the
    // freshly-applied code boots over HTTP. When empty it is derived from
    // APP_URL + health_check_path.
    'health_check_url' => env('CMS_UPDATE_HEALTH_URL', ''),

    'health_check_timeout' => (int) env('CMS_UPDATE_HEALTH_TIMEOUT', 10),

    // Hard limits for update-package extraction (zip-bomb protection).
    'max_uncompressed_mb' => (int) env('CMS_UPDATE_MAX_UNCOMPRESSED_MB', 1024),

    'max_archive_entries' => (int) env('CMS_UPDATE_MAX_ARCHIVE_ENTRIES', 20000),
];
