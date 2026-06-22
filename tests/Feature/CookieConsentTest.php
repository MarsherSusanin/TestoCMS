<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPage(string $slug): void
    {
        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Home',
            'slug' => $slug,
            'content_blocks' => [],
            'rendered_html' => '<p>x</p>',
        ]);
    }

    public function test_consent_banner_renders_when_enabled(): void
    {
        config()->set('cms.consent.enabled', true);
        config()->set('cms.consent.policy_url', '/en/privacy');
        $this->publishedPage('consent-on');

        $html = $this->get('/en/consent-on')->assertOk()->getContent();

        $this->assertStringContainsString('id="cms-consent"', $html);
        $this->assertStringContainsString('testocms_consent', $html);
        $this->assertStringContainsString('/en/privacy', $html);
    }

    public function test_consent_banner_absent_when_disabled(): void
    {
        config()->set('cms.consent.enabled', false);
        $this->publishedPage('consent-off');

        $html = $this->get('/en/consent-off')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="cms-consent"', $html);
    }
}
