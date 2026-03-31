<?php

return [
    'canonical_auto' => true,
    'default_robots' => [
        'index' => true,
        'follow' => true,
        'noarchive' => false,
        'nosnippet' => false,
    ],
    'sitemap' => [
        'enabled' => true,
        'max_urls_per_file' => (int) env('SEO_SITEMAP_MAX_URLS', 50000),
        'cache_ttl' => (int) env('SEO_SITEMAP_CACHE_TTL', 3600),
    ],
    'site' => [
        'name' => env('SEO_SITE_NAME', env('APP_NAME', 'TestoCMS')),
        'description' => env('SEO_SITE_DESCRIPTION', 'SEO-first CMS'),
        'organization_name' => env('SEO_ORGANIZATION_NAME', env('APP_NAME', 'TestoCMS')),
        'organization_logo' => env('SEO_ORGANIZATION_LOGO'),
    ],
];
