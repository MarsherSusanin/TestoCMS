<?php

return [
    'cms_version' => env('CMS_VERSION', '1.0.0'),

    'modules_root' => base_path(env('CMS_MODULES_ROOT', 'modules')),

    'bundled_root' => base_path(env('CMS_BUNDLED_MODULES_ROOT', 'bundled-modules')),

    'upload_tmp_root' => storage_path('app/private/module_uploads'),

    'local_install_roots' => array_values(array_filter(array_map(
        static fn (mixed $value): string => rtrim((string) $value, DIRECTORY_SEPARATOR),
        explode(',', (string) env('CMS_MODULE_LOCAL_ROOTS', base_path('modules-dev')))
    ))),

    'max_zip_size_mb' => (int) env('CMS_MODULE_MAX_ZIP_MB', 30),

    'allow_symlink_dev' => (bool) env('CMS_MODULE_ALLOW_SYMLINK_DEV', false),

    'cache_file' => base_path('bootstrap/cache/cms_modules.php'),

    'allowed_zip_mime' => [
        'application/zip',
        'application/x-zip-compressed',
        'multipart/x-zip',
        'application/octet-stream',
    ],
];
