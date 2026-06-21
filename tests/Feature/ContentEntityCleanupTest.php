<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Models\RedirectRule;
use App\Models\SeoOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentEntityCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug): Page
    {
        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Title',
            'slug' => $slug,
            'content_blocks' => [],
            'rendered_html' => '<p>x</p>',
        ]);

        return $page;
    }

    public function test_deleting_a_page_purges_orphan_rows_and_stale_redirects(): void
    {
        $page = $this->page('about');

        SeoOverride::query()->create(['entity_type' => 'page', 'entity_id' => $page->id, 'locale' => 'en', 'meta_title' => 'X']);
        PreviewToken::query()->create(['entity_type' => 'page', 'entity_id' => $page->id, 'token' => 'tok-'.$page->id, 'expires_at' => now()->addDay()]);
        PublishSchedule::query()->create(['entity_type' => 'page', 'entity_id' => $page->id, 'action' => 'unpublish', 'due_at' => now()->addDay()]);
        // Auto-301 that points at this page's URL — must be removed when the page goes.
        RedirectRule::query()->create(['from_path' => '/en/old-about', 'to_path' => '/en/about', 'http_code' => 301, 'is_active' => true]);

        $page->delete();

        $this->assertDatabaseMissing('seo_overrides', ['entity_type' => 'page', 'entity_id' => $page->id]);
        $this->assertDatabaseMissing('preview_tokens', ['entity_type' => 'page', 'entity_id' => $page->id]);
        $this->assertDatabaseMissing('publish_schedules', ['entity_type' => 'page', 'entity_id' => $page->id]);
        $this->assertDatabaseMissing('redirect_rules', ['to_path' => '/en/about']);
    }

    public function test_reverting_a_slug_does_not_create_a_redirect_loop(): void
    {
        $page = $this->page('first');
        $translation = $page->translations()->first();

        $translation->update(['slug' => 'second']);
        $this->assertDatabaseHas('redirect_rules', ['from_path' => '/en/first', 'to_path' => '/en/second']);

        // Revert. The inverse rule (/en/first -> /en/second) must be removed so
        // /en/first (now the live page) is not caught in a 301 loop.
        $translation->update(['slug' => 'first']);

        $this->assertDatabaseMissing('redirect_rules', ['from_path' => '/en/first', 'to_path' => '/en/second']);
        $this->assertDatabaseHas('redirect_rules', ['from_path' => '/en/second', 'to_path' => '/en/first']);
    }
}
