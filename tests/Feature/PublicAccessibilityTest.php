<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_has_skip_link_and_main_landmark(): void
    {
        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'A11y',
            'slug' => 'a11y',
            'content_blocks' => [],
            'rendered_html' => '<p>x</p>',
        ]);

        $html = $this->get('/en/a11y')->assertOk()->getContent();

        $this->assertStringContainsString('class="skip-link" href="#main"', $html);
        $this->assertStringContainsString('id="main"', $html);
        $this->assertStringContainsString(':focus-visible', $html);
    }
}
