<?php

return [
    'deployment_profile' => env('CMS_DEPLOYMENT_PROFILE', ''),
    'public_path' => env('LARAVEL_PUBLIC_PATH'),
    // Keep CLI bootstrap settings available with a cached configuration.
    'admin' => [
        'name' => env('CMS_ADMIN_NAME', 'Super Admin'),
        'login' => env('CMS_ADMIN_LOGIN', 'admin'),
        'email' => env('CMS_ADMIN_EMAIL', ''),
        'password' => env('CMS_ADMIN_PASSWORD', ''),
    ],
];
