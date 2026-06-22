<?php

namespace Tests\Unit;

use App\Modules\Setup\Services\EnvWriterService;
use Tests\TestCase;

class EnvWriterServiceTest extends TestCase
{
    public function test_generated_env_defaults_to_shared_hosting_safe_production_baseline(): void
    {
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
        ]);

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
        ]);

        $this->assertStringContainsString("CMS_SEED_DEMO_CONTENT=\"false\"\n", $content);
        $this->assertStringContainsString("CMS_DEPLOYMENT_PROFILE=\"docker_vps\"\n", $content);
        $this->assertStringContainsString("QUEUE_CONNECTION=\"database\"\n", $content);
        $this->assertStringContainsString("CACHE_STORE=\"file\"\n", $content);
        $this->assertStringContainsString("LARAVEL_PUBLIC_PATH=\"html_public\"\n", $content);
    }
}
