<?php

namespace Tests\Unit;

use App\Modules\Setup\Services\EnvWriterService;
use Dotenv\Dotenv;
use Tests\TestCase;

class EnvWriterServiceTest extends TestCase
{
    public function test_generated_env_defaults_to_shared_hosting_safe_production_baseline(): void
    {
        config()->set('setup.deployment_profile', '');

        $content = app(EnvWriterService::class)->buildEnvContent([
            'db_connection' => 'pgsql',
            'db_host' => 'db',
            'db_port' => '5432',
            'db_database' => 'testocms',
            'db_username' => 'testocms',
            'db_password' => 'testocms "quoted"',
            'app_name' => 'АНО Развитие',
            'app_url' => 'https://example.com',
            'supported_locales' => ['ru', 'en'],
            'default_locale' => 'ru',
            'admin_name' => 'Admin User',
            'admin_login' => 'admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Secret123!',
        ], '');

        $this->assertStringContainsString("APP_NAME=\"АНО Развитие\"\n", $content);
        $this->assertStringContainsString("DB_PASSWORD=\"testocms \\\"quoted\\\"\"\n", $content);
        $this->assertStringContainsString("MAIL_FROM_NAME=\"\${APP_NAME}\"\n", $content);
        $this->assertStringContainsString("CMS_ADMIN_NAME=\"Admin User\"\n", $content);
        $this->assertStringContainsString("CMS_SEED_DEMO_CONTENT=\"false\"\n", $content);
        $this->assertStringContainsString("CMS_DEPLOYMENT_PROFILE=\"shared_hosting\"\n", $content);
        $this->assertStringContainsString("QUEUE_CONNECTION=\"sync\"\n", $content);
        $this->assertStringContainsString("CACHE_STORE=\"file\"\n", $content);
        $this->assertStringContainsString("LARAVEL_PUBLIC_PATH=\"../public_html\"\n", $content);
    }

    public function test_generated_env_uses_database_queue_for_docker_vps_profile(): void
    {
        $content = app(EnvWriterService::class)->buildEnvContent([
            'deployment_profile' => 'docker_vps',
            'db_connection' => 'pgsql',
            'db_host' => 'db',
            'db_port' => '5432',
            'db_database' => 'testocms',
            'db_username' => 'testocms',
            'db_password' => 'testocms',
            'app_name' => 'TestoCMS',
            'app_url' => 'https://example.com',
            'supported_locales' => ['ru', 'en'],
            'default_locale' => 'ru',
            'admin_name' => 'Admin',
            'admin_login' => 'admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'Secret123!',
        ], '');

        $this->assertStringContainsString("CMS_SEED_DEMO_CONTENT=\"false\"\n", $content);
        $this->assertStringContainsString("CMS_DEPLOYMENT_PROFILE=\"docker_vps\"\n", $content);
        $this->assertStringContainsString("QUEUE_CONNECTION=\"database\"\n", $content);
        $this->assertStringContainsString("CACHE_STORE=\"file\"\n", $content);
        $this->assertStringContainsString("LARAVEL_PUBLIC_PATH=\"html_public\"\n", $content);
    }

    public function test_setup_preserves_integration_settings_keys_and_literal_passwords(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        $existing = "# provisioned environment\nAPP_KEY=\"$key\"\nCMS_CONTENT_API_KEY=existing-content-key\nMAIL_MAILER=smtp\nMAIL_PASSWORD='mail\$literal'\nOPENAI_API_KEY=existing-provider-key\nCMS_UPDATE_PUBLIC_KEY=existing-signing-key\nCUSTOM_INTEGRATION=kept\nDB_SCHEMA=custom_schema\n";
        $password = 'literal${APP_NAME} $cash "quoted" \\ value';
        $content = app(EnvWriterService::class)->buildEnvContent([
            'app_name' => 'New Name', 'db_password' => $password,
        ], $existing);
        $parsed = Dotenv::parse($content);
        $this->assertSame($key, $parsed['APP_KEY']);
        $this->assertSame('existing-content-key', $parsed['CMS_CONTENT_API_KEY']);
        $this->assertSame('smtp', $parsed['MAIL_MAILER']);
        $this->assertSame('mail$literal', $parsed['MAIL_PASSWORD']);
        $this->assertSame('existing-provider-key', $parsed['OPENAI_API_KEY']);
        $this->assertSame('existing-signing-key', $parsed['CMS_UPDATE_PUBLIC_KEY']);
        $this->assertSame('kept', $parsed['CUSTOM_INTEGRATION']);
        $this->assertSame('custom_schema', $parsed['DB_SCHEMA']);
        $this->assertSame($password, $parsed['DB_PASSWORD']);
    }

    public function test_multiline_assignments_are_replaced_or_preserved_as_whole_values(): void
    {
        $existing = "# keep exact CRLF\r\nAPP_NAME=\"Old\nName\"\nDB_PASSWORD=\"Old\nPassword\"\nCUSTOM_MULTILINE=\"first\nsecond\"\n";
        $content = app(EnvWriterService::class)->buildEnvContent([
            'app_name' => 'New', 'db_password' => "new\npassword",
        ], $existing);
        $parsed = Dotenv::parse($content);
        $this->assertSame('New', $parsed['APP_NAME']);
        $this->assertSame("new\npassword", $parsed['DB_PASSWORD']);
        $this->assertSame("first\nsecond", $parsed['CUSTOM_MULTILINE']);
        $this->assertStringContainsString("# keep exact CRLF\r\n", $content);
        $this->assertStringContainsString("CUSTOM_MULTILINE=\"first\nsecond\"\n", $content);
    }
}
