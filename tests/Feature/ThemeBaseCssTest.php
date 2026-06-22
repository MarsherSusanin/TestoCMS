<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeBaseCssTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_css_endpoint_is_served_immutably(): void
    {
        $response = $this->get('/cms/theme-base.css')->assertOk();

        $this->assertSame('text/css; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        // A base structural rule that is not part of the per-theme tokens.
        $this->assertStringContainsString('scroll-behavior: smooth', $response->getContent());
    }

    public function test_public_page_links_base_css_externally_and_inlines_only_tokens(): void
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
            'slug' => 'home',
            'content_blocks' => [],
            'rendered_html' => '<p>x</p>',
        ]);

        $html = $this->get('/en')->assertOk()->getContent();

        // External, cache-busted base stylesheet link.
        $this->assertStringContainsString('/cms/theme-base.css?v=', $html);
        // Per-theme tokens are still inlined.
        $this->assertStringContainsString(':root', $html);
        // The large structural base rules are NOT inlined into the page anymore.
        $this->assertStringNotContainsString('scroll-behavior: smooth', $html);
    }
}
