<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_and_sitemaps_are_available(): void
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
            'rendered_html' => '<p>Home</p>',
        ]);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap:', false);

        $this->get('/sitemap-index.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $this->get('/sitemaps/en.xml')
            ->assertOk();
    }
}
