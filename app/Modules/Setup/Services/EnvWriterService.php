<?php

namespace App\Modules\Setup\Services;

use Illuminate\Support\Str;

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
    public function buildEnvContent(array $data): string
    {
        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $deploymentProfile = $this->deploymentProfiles->normalize($data['deployment_profile'] ?? null);
        $profileConfig = $this->deploymentProfiles->resolve($deploymentProfile);

        $dbConnection = $data['db_connection'] ?? 'mysql';
        $dbPort = $data['db_port'] ?? ($dbConnection === 'pgsql' ? '5432' : '3306');

        $supportedLocales = implode(',', (array) ($data['supported_locales'] ?? ['ru', 'en']));
        $defaultLocale = $data['default_locale'] ?? 'ru';

        $lines = [
            'APP_NAME' => $data['app_name'] ?? 'TestoCMS',
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => $data['timezone'] ?? 'UTC',
            'APP_URL' => rtrim($data['app_url'] ?? 'https://localhost', '/'),
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
            'CMS_CONTENT_API_KEY' => Str::random(48),
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
            'DB_HOST' => $data['db_host'] ?? 'localhost',
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $data['db_database'] ?? '',
            'DB_USERNAME' => $data['db_username'] ?? '',
            'DB_PASSWORD' => $data['db_password'] ?? '',
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
            'MAIL_FROM_ADDRESS' => '"'.($data['admin_email'] ?? 'hello@example.com').'"',
            'MAIL_FROM_NAME' => '"${APP_NAME}"',
            '',
            'SEO_SITE_NAME' => '"${APP_NAME}"',
            'SEO_SITE_DESCRIPTION' => '"SEO-first CMS"',
            'SEO_ORGANIZATION_NAME' => '"${APP_NAME}"',
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
            'CMS_ADMIN_NAME' => '"'.($data['admin_name'] ?? 'Super Admin').'"',
            'CMS_ADMIN_LOGIN' => $data['admin_login'] ?? 'admin',
            'CMS_ADMIN_EMAIL' => $data['admin_email'] ?? 'admin@example.com',
            'CMS_ADMIN_PASSWORD' => '"'.addcslashes($data['admin_password'] ?? '', '"\\').'"',
            '',
            'VITE_APP_NAME' => '"${APP_NAME}"',
        ];

        return $this->renderLines($lines);
    }

    /**
     * Write content to .env file.
     */
    public function writeEnvFile(string $content): void
    {
        file_put_contents(base_path('.env'), $content);
    }

    /**
     * Create the installed marker file.
     */
    public function markInstalled(): void
    {
        $path = storage_path('installed');
        file_put_contents($path, json_encode([
            'installed_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
                $output .= $key.'='.$value."\n";
            }
        }

        return $output;
    }
}
