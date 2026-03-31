<?php

namespace App\Modules\Setup\Services;

class SystemCheckService
{
    /**
     * @return array<string, array{passed: bool, label: string, detail: string}>
     */
    public function runAll(): array
    {
        return [
            'php_version' => $this->checkPhpVersion(),
            'pdo_database' => $this->checkDatabaseDriverSupport(),
            'mbstring' => $this->checkExtension('mbstring', 'Mbstring'),
            'intl' => $this->checkExtension('intl', 'Intl'),
            'gd' => $this->checkExtension('gd', 'GD'),
            'bcmath' => $this->checkExtension('bcmath', 'BCMath'),
            'zip' => $this->checkExtension('zip', 'Zip'),
            'exif' => $this->checkExtension('exif', 'Exif'),
            'openssl' => $this->checkExtension('openssl', 'OpenSSL'),
            'curl' => $this->checkExtension('curl', 'cURL'),
            'fileinfo' => $this->checkExtension('fileinfo', 'Fileinfo'),
            'storage_writable' => $this->checkWritable(storage_path(), 'storage/'),
            'bootstrap_cache' => $this->checkWritable(base_path('bootstrap/cache'), 'bootstrap/cache/'),
            'env_writable' => $this->checkEnvWritable(),
        ];
    }

    public function allRequiredPassed(): bool
    {
        foreach ($this->runAll() as $key => $check) {
            if (str_contains($key, 'optional')) {
                continue;
            }
            if ($check['optional'] ?? false) {
                continue;
            }
            if (! $check['passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Auto-detect system parameters that don't need user input.
     *
     * @return array<string, mixed>
     */
    public function autoDetect(): array
    {
        $isHttps = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $scheme = $isHttps ? 'https' : 'http';

        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'https' => $isHttps,
            'app_url' => $scheme.'://'.$host,
            'timezone' => date_default_timezone_get(),
            'has_pgsql' => $this->hasExtension('pdo_pgsql'),
            'has_mysql' => $this->hasExtension('pdo_mysql'),
            'queue_connection' => 'sync',
        ];
    }

    private function checkPhpVersion(): array
    {
        $passed = version_compare(PHP_VERSION, '8.2.0', '>=');

        return [
            'passed' => $passed,
            'label' => 'PHP ≥ 8.2',
            'detail' => PHP_VERSION,
            'optional' => false,
        ];
    }

    private function checkExtension(string $ext, string $label): array
    {
        return [
            'passed' => $this->hasExtension($ext),
            'label' => $label,
            'detail' => $this->extensionDetail($ext),
            'optional' => false,
        ];
    }

    private function checkDatabaseDriverSupport(): array
    {
        $hasMysql = $this->hasExtension('pdo_mysql');
        $hasPgsql = $this->hasExtension('pdo_pgsql');
        $availableDrivers = array_values(array_filter([
            $hasMysql ? 'pdo_mysql' : null,
            $hasPgsql ? 'pdo_pgsql' : null,
        ]));

        return [
            'passed' => $hasMysql || $hasPgsql,
            'label' => 'PDO MySQL or PDO PostgreSQL',
            'detail' => $availableDrivers !== [] ? implode(', ', $availableDrivers) : 'missing',
            'optional' => false,
        ];
    }

    private function checkWritable(string $path, string $label): array
    {
        $writable = $this->isWritablePath($path);

        return [
            'passed' => $writable,
            'label' => $label.' writable',
            'detail' => $writable ? 'OK' : 'not writable',
            'optional' => false,
        ];
    }

    private function checkEnvWritable(): array
    {
        $writable = $this->isEnvFileWritable();

        return [
            'passed' => $writable,
            'label' => '.env writable',
            'detail' => $writable ? 'OK' : 'not writable',
            'optional' => false,
        ];
    }

    protected function hasExtension(string $ext): bool
    {
        return extension_loaded($ext);
    }

    protected function extensionDetail(string $ext): string
    {
        return $this->hasExtension($ext) ? phpversion($ext) ?: 'loaded' : 'missing';
    }

    protected function isWritablePath(string $path): bool
    {
        return is_dir($path) && is_writable($path);
    }

    protected function isEnvFileWritable(): bool
    {
        $envPath = base_path('.env');
        $dir = dirname($envPath);

        return is_writable($dir) || (file_exists($envPath) && is_writable($envPath));
    }
}
