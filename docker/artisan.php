<?php

require '/var/www/html/vendor/autoload.php';
Dotenv\Dotenv::createUnsafeImmutable('/var/www/html')->load();
$privateKeys = '/var/www/html/storage/app/private/docker-bootstrap-keys.json';
if (is_file($privateKeys)) {
    foreach (json_decode((string) file_get_contents($privateKeys), true, 512, JSON_THROW_ON_ERROR) as $key => $value) {
        if (in_array($key, ['APP_KEY', 'CMS_CONTENT_API_KEY'], true) && trim((string) getenv($key)) === '') {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }
}

$selectRuntimeUser = require __DIR__.'/runtime-user.php';
$selectRuntimeUser();
require dirname(__DIR__).'/artisan';
