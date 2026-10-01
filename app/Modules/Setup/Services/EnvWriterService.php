<?php

namespace App\Modules\Setup\Services;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Illuminate\Support\Str;
use RuntimeException;

class EnvWriterService
{
    public function __construct(
        private readonly DeploymentProfileService $deploymentProfiles,
    ) {}

    /**
     * Build .env content from wizard data.
     *
     * @param  array<string, mixed>  $data
     */
    public function buildEnvContent(array $data, ?string $existingContent = null): string
    {
        $existingContent ??= is_file(app()->environmentFilePath()) ? (string) file_get_contents(app()->environmentFilePath()) : '';
        $existing = Dotenv::parse($existingContent);
        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $deploymentProfile = $this->deploymentProfiles->normalize($data['deployment_profile'] ?? null);
        $profileConfig = $this->deploymentProfiles->resolve($deploymentProfile);

        $dbConnection = $data['db_connection'] ?? $existing['DB_CONNECTION'] ?? config('database.default', 'mysql');
        $connection = (array) config('database.connections.'.$dbConnection, []);
        $dbPort = $data['db_port'] ?? $existing['DB_PORT'] ?? $connection['port'] ?? ($dbConnection === 'pgsql' ? '5432' : '3306');

        $supportedLocales = implode(',', (array) ($data['supported_locales'] ?? ['ru', 'en']));
        $defaultLocale = $data['default_locale'] ?? 'ru';

        $lines = [
            'APP_NAME' => $data['app_name'] ?? 'TestoCMS',
            'APP_ENV' => $deploymentProfile === DeploymentProfileService::LOCAL ? 'local' : 'production',
            'APP_KEY' => trim((string) ($existing['APP_KEY'] ?? '')) !== '' ? $existing['APP_KEY'] : (config('app.key') ?: $appKey),
            'APP_DEBUG' => $deploymentProfile === DeploymentProfileService::LOCAL ? 'true' : 'false',
            'APP_TIMEZONE' => $data['timezone'] ?? 'UTC',
            'APP_URL' => rtrim($data['app_url'] ?? 'https://localhost', '/'),
            'LARAVEL_PUBLIC_PATH' => $data['public_path'] ?? ($existing['LARAVEL_PUBLIC_PATH'] ?? $profileConfig['public_path']),
            '',
            'APP_LOCALE' => $defaultLocale,
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_FAKER_LOCALE' => 'en_US',
            '',
            'CMS_SUPPORTED_LOCALES' => $supportedLocales,
            'CMS_DEFAULT_LOCALE' => $defaultLocale,
            'CMS_POST_URL_PREFIX' => 'blog',
            'CMS_CATEGORY_URL_PREFIX' => 'category',
            'CMS_DEFAULT_PER_PAGE' => '20',
            'CMS_MAX_PER_PAGE' => '100',
            'CMS_FULL_PAGE_CACHE_TTL' => '300',
            'CMS_SLUG_CACHE_TTL' => '300',
            'CMS_REVISION_LIMIT' => '10',
            'CMS_SAFE_EMBED_DOMAINS' => 'youtube.com,youtu.be,vimeo.com,maps.google.com',
            'CMS_CONTENT_API_KEY' => trim((string) ($existing['CMS_CONTENT_API_KEY'] ?? '')) !== '' ? $existing['CMS_CONTENT_API_KEY'] : Str::random(48),
            'CMS_CONTENT_API_RATE_LIMIT' => '120',
            'CMS_SEED_DEMO_CONTENT' => 'false',
            'CMS_DEPLOYMENT_PROFILE' => $deploymentProfile,
            '',
            'APP_MAINTENANCE_DRIVER' => 'file',
            '',
            'BCRYPT_ROUNDS' => '12',
            '',
            'LOG_CHANNEL' => 'stack',
            'LOG_STACK' => 'daily',
            'LOG_DEPRECATIONS_CHANNEL' => 'null',
            'LOG_LEVEL' => 'warning',
            '',
            'DB_CONNECTION' => $dbConnection,
            'DB_HOST' => $data['db_host'] ?? $connection['host'] ?? 'localhost',
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $data['db_database'] ?? $connection['database'] ?? '',
            'DB_USERNAME' => $data['db_username'] ?? $connection['username'] ?? '',
            'DB_PASSWORD' => $data['db_password'] ?? $connection['password'] ?? '',
            '',
            'SESSION_DRIVER' => 'database',
            'SESSION_LIFETIME' => '120',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_PATH' => '/',
            'SESSION_DOMAIN' => 'null',
            'SESSION_SECURE_COOKIE' => ($data['https'] ?? false) ? 'true' : 'false',
            'SESSION_SAME_SITE' => 'lax',
            '',
            'BROADCAST_CONNECTION' => 'log',
            'FILESYSTEM_DISK' => 'public',
            'QUEUE_CONNECTION' => $profileConfig['queue_connection'],
            '',
            'CACHE_STORE' => $profileConfig['cache_store'],
            'CACHE_PREFIX' => '',
            '',
            'MAIL_MAILER' => 'log',
            'MAIL_SCHEME' => 'null',
            'MAIL_HOST' => '127.0.0.1',
            'MAIL_PORT' => '2525',
            'MAIL_USERNAME' => 'null',
            'MAIL_PASSWORD' => 'null',
            'MAIL_FROM_ADDRESS' => $data['admin_email'] ?? 'hello@example.com',
            'MAIL_FROM_NAME' => '${APP_NAME}',
            '',
            'SEO_SITE_NAME' => '${APP_NAME}',
            'SEO_SITE_DESCRIPTION' => 'SEO-first CMS',
            'SEO_ORGANIZATION_NAME' => '${APP_NAME}',
            'SEO_ORGANIZATION_LOGO' => '',
            'SEO_SITEMAP_MAX_URLS' => '50000',
            'SEO_SITEMAP_CACHE_TTL' => '3600',
            '',
            'SECURITY_CSP_ENABLED' => 'true',
            'SECURITY_CSP_REPORT_ONLY' => 'false',
            '',
            'LLM_DEFAULT_PROVIDER' => 'openai',
            'LLM_RATE_LIMIT_PER_MINUTE' => '30',
            'LLM_MAX_INPUT_CHARS' => '12000',
            'LLM_MAX_OUTPUT_CHARS' => '24000',
            'OPENAI_API_KEY' => '',
            'OPENAI_BASE_URL' => 'https://api.openai.com/v1',
            'OPENAI_MODEL' => 'gpt-4.1-mini',
            'OPENAI_TIMEOUT' => '30',
            'ANTHROPIC_API_KEY' => '',
            'ANTHROPIC_BASE_URL' => 'https://api.anthropic.com/v1',
            'ANTHROPIC_MODEL' => 'claude-3-5-sonnet-latest',
            'ANTHROPIC_TIMEOUT' => '30',
            'ANTHROPIC_VERSION' => '2023-06-01',
            '',
            'CMS_ADMIN_NAME' => $data['admin_name'] ?? 'Super Admin',
            'CMS_ADMIN_LOGIN' => $data['admin_login'] ?? 'admin',
            'CMS_ADMIN_EMAIL' => $data['admin_email'] ?? 'admin@example.com',
            'CMS_ADMIN_PASSWORD' => $data['admin_password'] ?? '',
            '',
            'VITE_APP_NAME' => '${APP_NAME}',
        ];

        $explicit = [
            'app_name' => 'APP_NAME', 'app_url' => 'APP_URL', 'timezone' => 'APP_TIMEZONE',
            'db_connection' => 'DB_CONNECTION', 'db_host' => 'DB_HOST', 'db_port' => 'DB_PORT',
            'db_database' => 'DB_DATABASE', 'db_username' => 'DB_USERNAME', 'db_password' => 'DB_PASSWORD',
            'supported_locales' => 'CMS_SUPPORTED_LOCALES', 'default_locale' => 'CMS_DEFAULT_LOCALE',
            'admin_name' => 'CMS_ADMIN_NAME', 'admin_login' => 'CMS_ADMIN_LOGIN',
            'admin_email' => 'CMS_ADMIN_EMAIL', 'admin_password' => 'CMS_ADMIN_PASSWORD',
            'deployment_profile' => 'CMS_DEPLOYMENT_PROFILE',
            'public_path' => 'LARAVEL_PUBLIC_PATH',
        ];
        $replace = ['APP_KEY', 'CMS_CONTENT_API_KEY'];
        foreach ($explicit as $input => $key) {
            if (array_key_exists($input, $data)) {
                $replace[] = $key;
            }
        }
        if (array_key_exists('default_locale', $data)) {
            $replace[] = 'APP_LOCALE';
        }
        if (array_key_exists('deployment_profile', $data)
            && ($existing['CMS_DEPLOYMENT_PROFILE'] ?? null) !== $deploymentProfile) {
            array_push($replace, 'QUEUE_CONNECTION', 'CACHE_STORE');
        }
        foreach ($lines as $key => $value) {
            if (is_string($key) && array_key_exists($key, $existing) && ! in_array($key, $replace, true)) {
                unset($lines[$key]);
            }
        }
        // Preserve unknown integrations, comments and quoting byte-for-byte.
        $remaining = $lines;
        $output = '';
        $rawLines = preg_split('/(?<=\n)/', $existingContent, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        for ($index = 0; $index < count($rawLines); $index++) {
            $line = $rawLines[$index];
            if (preg_match('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/', $line, $match)
            ) {
                // Quoted dotenv values may span multiple physical lines. Consume
                // the whole assignment before replacing it or retaining its raw text.
                while (true) {
                    try {
                        if (array_key_exists($match[1], Dotenv::parse($line))) {
                            break;
                        }
                        // phpdotenv leaves an unterminated multiline buffer out
                        // of the parsed result until the closing quote arrives.
                        if (! isset($rawLines[$index + 1])) {
                            throw new RuntimeException('Incomplete environment assignment.');
                        }
                        $line .= $rawLines[++$index];
                    } catch (InvalidFileException $exception) {
                        if (! isset($rawLines[$index + 1])) {
                            throw $exception;
                        }
                        $line .= $rawLines[++$index];
                    }
                }
            }
            if (isset($match[1]) && array_key_exists($match[1], $lines)) {
                $key = $match[1];
                if (! array_key_exists($key, $remaining)) {
                    continue;
                }
                $output .= $key.'='.$this->quoteEnvValue((string) $remaining[$key], $this->isTemplateKey($key))."\n";
                unset($remaining[$key]);
            } else {
                $output .= $line;
            }
            $match = [];
        }
        if ($output !== '' && ! str_ends_with($output, "\n")) {
            $output .= "\n";
        }

        return $output.$this->renderLines($remaining);
    }

    /**
     * Write content to .env file.
     */
    public function writeEnvFile(string $content): void
    {
        $path = app()->environmentFilePath();
        $temporary = tempnam(dirname($path), '.cms-env-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create temporary environment file.');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false || ! chmod($temporary, 0600) || ! rename($temporary, $path)) {
                throw new RuntimeException('Cannot replace the environment file.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Create the installed marker file.
     */
    public function markInstalled(): void
    {
        $path = storage_path('installed');
        $content = json_encode(array_merge([
            'installed_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
        ], app(InstallationIdentityService::class)->bind()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = tempnam(dirname($path), '.cms-installed-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create the installation marker.');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false || ! rename($temporary, $path)) {
                throw new RuntimeException('Cannot write the installation marker.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Check if CMS is already installed.
     */
    public static function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }

    /**
     * Remove the installed marker (for --redo).
     */
    public static function removeInstalledMarker(): void
    {
        $path = storage_path('installed');
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Test database connection.
     *
     * @param  array<string, string>  $params
     * @return array{ok: bool, error: string|null}
     */
    public function testDatabaseConnection(array $params): array
    {
        $driver = $params['driver'] ?? 'mysql';

        try {
            if ($driver === 'pgsql') {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $params['host'] ?? 'localhost',
                    $params['port'] ?? '5432',
                    $params['database'] ?? '',
                );
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $params['host'] ?? 'localhost',
                    $params['port'] ?? '3306',
                    $params['database'] ?? '',
                );
            }

            new \PDO(
                $dsn,
                $params['username'] ?? '',
                $params['password'] ?? '',
                [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
            );

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<int|string, string>  $lines
     */
    private function renderLines(array $lines): string
    {
        $output = '';

        foreach ($lines as $key => $value) {
            if (is_int($key) && $value === '') {
                $output .= "\n";
            } else {
                $output .= $key.'='.$this->quoteEnvValue($value, $this->isTemplateKey((string) $key))."\n";
            }
        }

        return $output;
    }

    private function isTemplateKey(string $key): bool
    {
        return in_array($key, ['MAIL_FROM_NAME', 'SEO_SITE_NAME', 'SEO_ORGANIZATION_NAME', 'VITE_APP_NAME'], true);
    }

    private function quoteEnvValue(string $value, bool $interpolate = false): string
    {
        return '"'.str_replace(
            ['\\', '"', "\n", "\r", '$'],
            ['\\\\', '\\"', '\\n', '\\r', $interpolate ? '$' : '\\$'],
            $value,
        ).'"';
    }
}
