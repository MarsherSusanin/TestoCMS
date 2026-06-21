<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sitemap and llms.txt must not advertise content that should not be
 * crawled: noindex translations and future-dated (embargoed) entries.
 */
class SeoSitemapLeakTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPage(string $slug, array $overrides = [], array $translation = []): void
    {
        $page = Page::query()->create(array_merge([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now()->subDay(),
        ], $overrides));

        PageTranslation::query()->create(array_merge([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => ucfirst($slug),
            'slug' => $slug,
            'content_blocks' => [],
            'rendered_html' => '<p>x</p>',
        ], $translation));
    }

    public function test_sitemap_excludes_noindex_and_embargoed_pages(): void
    {
        $this->publishedPage('visible');
        $this->publishedPage('noindexed', translation: ['robots_directives' => ['index' => false, 'follow' => true]]);
        $this->publishedPage('embargoed', overrides: ['published_at' => now()->addDay()]);

        $xml = $this->get('/sitemaps/en.xml')->assertOk()->streamedContent();

        $this->assertStringContainsString('/en/visible', $xml);
        $this->assertStringNotContainsString('/en/noindexed', $xml);
        $this->assertStringNotContainsString('/en/embargoed', $xml);
    }

    public function test_llms_txt_excludes_noindex_and_embargoed_pages(): void
    {
        config()->set('cms.default_locale', 'en');

        $this->publishedPage('llms-visible');
        $this->publishedPage('llms-noindexed', translation: ['robots_directives' => ['index' => false, 'follow' => true]]);
        $this->publishedPage('llms-embargoed', overrides: ['published_at' => now()->addDay()]);

        $txt = $this->get('/llms.txt')->assertOk()->streamedContent();

        $this->assertStringContainsString('/en/llms-visible', $txt);
        $this->assertStringNotContainsString('/en/llms-noindexed', $txt);
        $this->assertStringNotContainsString('/en/llms-embargoed', $txt);
    }
}
