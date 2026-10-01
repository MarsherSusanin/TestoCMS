<?php

use Dotenv\Exception\InvalidFileException;

// Run dotenv as data, never as shell code. Export parsed values to the final process.
require '/var/www/html/vendor/autoload.php';

try {
    $inherited = getenv();
    Dotenv\Dotenv::createUnsafeImmutable('/var/www/html')->load();
    $localBootstrap = getenv('ENTRYPOINT_ALLOW_DOCKER_ENV_BOOTSTRAP') === '1';
    $lock = $localBootstrap ? fopen('/var/www/html/storage/app/private/bootstrap.lock', 'c') : null;
    if ($localBootstrap && ($lock === false || ! flock($lock, LOCK_EX))) {
        throw new RuntimeException('Cannot lock local container bootstrap.');
    }
    try {
        $fresh = Dotenv\Dotenv::parse((string) file_get_contents('/var/www/html/.env'));
        $secretsPath = '/var/www/html/storage/app/private/docker-bootstrap-keys.json';
        $secrets = is_file($secretsPath) ? json_decode((string) file_get_contents($secretsPath), true, 512, JSON_THROW_ON_ERROR) : [];
        if (! is_array($secrets)) {
            throw new RuntimeException('Invalid local bootstrap keys; restore the matching storage volume.');
        }
        foreach (['APP_KEY', 'CMS_CONTENT_API_KEY'] as $key) {
            $value = trim((string) (($inherited[$key] ?? '') !== '' ? $inherited[$key] : ($fresh[$key] ?? '')));
            if ($value === '') {
                if (! $localBootstrap) {
                    throw new RuntimeException($key.' must be set in the provided environment before starting production containers.');
                }
                if (is_file('/var/www/html/storage/installed') && empty($secrets[$key])) {
                    throw new RuntimeException('Local installation keys are missing. Restore the matching storage volume; no reset was performed.');
                }
                $value = $secrets[$key] ?? ($key === 'APP_KEY' ? 'base64:'.base64_encode(random_bytes(32)) : bin2hex(random_bytes(24)));
                $secrets[$key] = $value;
            }
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        if ($localBootstrap) {
            $temporary = tempnam(dirname($secretsPath), '.docker-keys-');
            if ($temporary === false || ! chmod($temporary, 0600) || file_put_contents($temporary, json_encode($secrets, JSON_THROW_ON_ERROR), LOCK_EX) === false || ! rename($temporary, $secretsPath)) {
                throw new RuntimeException('Cannot persist private local bootstrap secrets.');
            }
            chown($secretsPath, 'www-data');
            chgrp($secretsPath, 'www-data');
            chown('/var/www/html/storage/app/private/bootstrap.lock', 'www-data');
            chmod('/var/www/html/storage/app/private/bootstrap.lock', 0600);

        }
    } finally {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    if (getenv('WAIT_FOR_DB') !== '0') {
        $driver = getenv('DB_CONNECTION') ?: 'pgsql';
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
        $database = getenv('DB_DATABASE') ?: '';
        $dsn = $driver === 'sqlite' ? 'sqlite:'.$database : $driver.':host='.$host.';port='.$port.';dbname='.$database;
        $deadline = time() + 60;
        while (true) {
            try {
                new PDO($dsn, getenv('DB_USERNAME') ?: '', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_TIMEOUT => 3]);
                break;
            } catch (Throwable $e) {
                if (time() >= $deadline) {
                    throw new RuntimeException('Database did not become ready within 60 seconds.');
                }
                sleep(2);
            }
        }
    }

    $run = static function (array $arguments): void {
        $process = proc_open(array_merge([PHP_BINARY, '/var/www/html/docker/artisan.php'], $arguments), [STDIN, STDOUT, STDERR], $pipes, '/var/www/html');
        if (! is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Container initialization command failed: '.($arguments[0] ?? 'artisan'));
        }
    };
    if (getenv('AUTO_SETUP') === '1') {
        $run(['config:clear']);
        $run(['cms:setup', '--from-env', '--no-interaction']);
        // Idempotent setup returns early; rebuild the cleared cache so fresh
        // CLI/FPM processes can still read privately generated local keys.
        $run(['config:cache']);
    }
    if (getenv('AUTO_STORAGE_LINK') === '1') {
        // The public media HTTP endpoint remains available if symlinks are disabled.
        $process = proc_open([PHP_BINARY, '/var/www/html/docker/prepare-media.php'], [STDIN, STDOUT, STDERR], $pipes, '/var/www/html');
        if (! is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Cannot prepare public media storage.');
        }
    }

    $arguments = array_slice($argv, 1);
    $command = array_shift($arguments);
    if ($command === null) {
        throw new RuntimeException('No container command provided.');
    }
    $binary = null;
    foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
        $candidate = str_contains($command, '/') ? $command : $directory.'/'.$command;
        if (is_file($candidate) && is_executable($candidate)) {
            $binary = $candidate;
            break;
        }
    }
    if ($binary === null || ! function_exists('pcntl_exec')) {
        throw new RuntimeException('Cannot execute container command: '.$command);
    }
    if ($binary === PHP_BINARY || basename($binary) === 'php') {
        $selectRuntimeUser = require __DIR__.'/runtime-user.php';
        $selectRuntimeUser();
    }
    pcntl_exec($binary, $arguments);
    throw new RuntimeException('Container process replacement failed.');
} catch (Throwable $e) {
    // Connection failures intentionally omit the DSN/password.
    fwrite(STDERR, '[entrypoint] '.($e instanceof InvalidFileException ? 'Invalid dotenv syntax. Check the environment file without exposing credentials.' : $e->getMessage())."\n");
    exit(1);
}
