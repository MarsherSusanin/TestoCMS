<?php

// PostgreSQL credentials use the same dotenv parser as PHP; Compose's dollar
// escaping differs from phpdotenv and must not transform credential bytes.
require '/var/www/html/vendor/autoload.php';
try {
    $environment = Dotenv\Dotenv::parse((string) file_get_contents('/var/www/html/.env'));
    if (($environment['DB_CONNECTION'] ?? '') !== 'pgsql') {
        throw new RuntimeException('Docker PostgreSQL profiles require DB_CONNECTION=pgsql.');
    }
    foreach (['database' => 'DB_DATABASE', 'user' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $file => $key) {
        $value = (string) ($environment[$key] ?? '');
        if ($value === '' || str_contains($value, "\0")) {
            throw new RuntimeException('Missing or invalid PostgreSQL credential: '.$key);
        }
        $path = '/run/cms-db/'.$file;
        $temporary = tempnam('/run/cms-db', '.credential-');
        if ($temporary === false || file_put_contents($temporary, $value, LOCK_EX) !== strlen($value) || ! chmod($temporary, 0600) || ! rename($temporary, $path)) {
            throw new RuntimeException('Cannot prepare private PostgreSQL credentials.');
        }
    }
    echo "PostgreSQL credentials prepared without interpolation.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "PostgreSQL credential preparation failed; check environment syntax and private volume permissions.\n");
    exit(1);
}
