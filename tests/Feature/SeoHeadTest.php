<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the public <head> SEO/social output: self-canonical, OpenGraph &
 * Twitter cards, and hreflang alternates including x-default.
 */
class SeoHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_emits_og_twitter_canonical_and_hreflang(): void
    {
        config()->set('seo.site.name', 'TestoCMS Site');

        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);

        foreach (['en' => 'About us', 'ru' => 'О нас'] as $locale => $title) {
            PageTranslation::query()->create([
                'page_id' => $page->id,
                'locale' => $locale,
                'title' => $title,
                'slug' => 'about',
                'content_blocks' => [],
                'rendered_html' => '<p>About</p>',
                'meta_description' => 'About page description',
                'canonical_url' => '/'.$locale.'/about',
            ]);
        }

        $html = $this->get('/en/about')->assertOk()->getContent();

        // Canonical (self, same host)
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/en/about').'"', $html);

        // OpenGraph
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:url"', $html);
        $this->assertStringContainsString('property="og:type" content="website"', $html);
        $this->assertStringContainsString('property="og:site_name" content="TestoCMS Site"', $html);
        $this->assertStringContainsString('property="og:locale:alternate" content="ru"', $html);

        // Twitter card
        $this->assertStringContainsString('name="twitter:card"', $html);
        $this->assertStringContainsString('name="twitter:title"', $html);

        // hreflang alternates + x-default
        $this->assertStringContainsString('hreflang="en"', $html);
        $this->assertStringContainsString('hreflang="ru"', $html);
        $this->assertStringContainsString('hreflang="x-default"', $html);

        // JSON-LD @graph wiring the previously-dead Organization/WebSite nodes
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@graph"', $html);
        $this->assertStringContainsString('"Organization"', $html);
        $this->assertStringContainsString('"WebSite"', $html);
    }

    public function test_faq_block_emits_faqpage_structured_data(): void
    {
        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'FAQ Page',
            'slug' => 'faq',
            'content_blocks' => [[
                'type' => 'faq',
                'data' => ['items' => [
                    ['question' => 'What is it?', 'answer' => 'A CMS.'],
                ]],
            ]],
            'rendered_html' => '<p>faq</p>',
        ]);

        $html = $this->get('/en/faq')->assertOk()->getContent();

        $this->assertStringContainsString('"FAQPage"', $html);
        $this->assertStringContainsString('What is it?', $html);
    }
}
