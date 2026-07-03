<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SeoOverride;
use App\Models\User;
use App\Modules\Content\Services\PageContentService;
use App\Modules\SEO\Services\SeoCacheKeys;
use App\Modules\SEO\Services\SeoResolverService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeoCacheHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPage(string $slug, array $translationOverrides = []): Page
    {
        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now()->subMinute(),
        ]);
        PageTranslation::query()->create(array_merge([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Page '.$slug,
            'slug' => $slug,
            'content_blocks' => [],
            'rendered_html' => '<p>x</p>',
        ], $translationOverrides));

        return $page;
    }

    public function test_full_page_cache_hit_preserves_x_robots_tag(): void
    {
        $this->publishedPage('robots-cached', [
            'robots_directives' => ['index' => false, 'follow' => false],
        ]);

        $miss = $this->get('/en/robots-cached')->assertOk();
        $this->assertSame('MISS', $miss->headers->get('X-TestoCMS-Cache'));
        $this->assertSame('noindex, nofollow', $miss->headers->get('X-Robots-Tag'));

        // A noindex page must stay noindex when served from the page cache.
        $hit = $this->get('/en/robots-cached')->assertOk();
        $this->assertSame('HIT', $hit->headers->get('X-TestoCMS-Cache'));
        $this->assertSame('noindex, nofollow', $hit->headers->get('X-Robots-Tag'));
    }

    public function test_content_save_busts_sitemap_and_llms_caches(): void
    {
        config()->set('cms.default_locale', 'en');
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->create([
            'name' => 'Admin', 'login' => 'seo_cache_admin', 'status' => 'active',
            'email' => 'seo_cache_admin@testocms.local', 'password' => Hash::make('password'),
        ]);
        $admin->assignRole('superadmin');

        $this->publishedPage('first-page');

        // Warm the caches: the sitemap knows only about first-page.
        $warm = $this->get('/sitemaps/en.xml')->assertOk()->getContent();
        $this->get('/llms.txt')->assertOk();
        $this->assertStringContainsString('/en/first-page', $warm);
        $this->assertTrue(Cache::has(SeoCacheKeys::sitemap('en')));
        $this->assertTrue(Cache::has(SeoCacheKeys::llms('en')));

        // A real content mutation through the service layer must drop them.
        app(PageContentService::class)->createFromValidated([
            'status' => 'published',
            'page_type' => 'landing',
            'translations' => [[
                'locale' => 'en',
                'title' => 'Second page',
                'slug' => 'second-page',
                'content_blocks' => [],
            ]],
        ], $admin, ['require_default_locale' => false]);
        Page::query()->latest('id')->firstOrFail()
            ->forceFill(['published_at' => now()->subMinute()])->save();

        $this->assertFalse(Cache::has(SeoCacheKeys::sitemap('en')));
        $this->assertFalse(Cache::has(SeoCacheKeys::llms('en')));

        $fresh = $this->get('/sitemaps/en.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/en/second-page', $fresh);
    }

    public function test_sitemap_index_lastmod_reflects_latest_content_change(): void
    {
        $page = $this->publishedPage('lastmod-page');
        DB::table('page_translations')
            ->where('page_id', $page->id)
            ->update(['updated_at' => '2026-01-15 10:00:00']);

        $xml = $this->get('/sitemap-index.xml')->assertOk()->getContent();

        // The en entry advertises the real content timestamp, not "now" on
        // every crawl. (Locales without content legitimately fall back to now.)
        $this->assertStringContainsString('/sitemaps/en.xml</loc><lastmod>2026-01-15', $xml);
    }

    public function test_meta_lengths_are_clamped_at_resolve_time(): void
    {
        $page = $this->publishedPage('meta-clamp');

        $seo = app(SeoResolverService::class)->resolve('page', $page->id, 'en', [
            'meta_title' => str_repeat('T', 400),
            'meta_description' => str_repeat('D', 3000),
        ]);

        $this->assertLessThanOrEqual(SeoResolverService::META_TITLE_MAX, mb_strlen($seo['meta_title']));
        $this->assertLessThanOrEqual(SeoResolverService::META_DESCRIPTION_MAX, mb_strlen($seo['meta_description']));
    }

    public function test_seo_override_cache_is_invalidated_on_save(): void
    {
        $page = $this->publishedPage('override-cache');
        $resolver = app(SeoResolverService::class);

        // Prime the "no override" cache entry.
        $before = $resolver->resolve('page', $page->id, 'en', ['meta_title' => 'Fallback']);
        $this->assertSame('Fallback', $before['meta_title']);

        SeoOverride::query()->create([
            'entity_type' => 'page',
            'entity_id' => $page->id,
            'locale' => 'en',
            'meta_title' => 'Overridden',
        ]);

        // The model event must bust the cached lookup immediately.
        $after = $resolver->resolve('page', $page->id, 'en', ['meta_title' => 'Fallback']);
        $this->assertSame('Overridden', $after['meta_title']);
    }

    public function test_deleting_the_parent_entity_busts_the_override_cache(): void
    {
        $page = $this->publishedPage('override-delete');
        SeoOverride::query()->create([
            'entity_type' => 'page',
            'entity_id' => $page->id,
            'locale' => 'en',
            'meta_title' => 'Phantom',
        ]);
        $resolver = app(SeoResolverService::class);

        // Prime the cache with the override present.
        $this->assertSame('Phantom', $resolver->resolve('page', $page->id, 'en', ['meta_title' => 'Fallback'])['meta_title']);

        // Deleting the page purges seo_overrides via a query-builder delete (no
        // model event) — the observer must forget the resolver cache, else a
        // reused id would serve the deleted override's meta for the full TTL.
        $page->delete();

        $after = $resolver->resolve('page', $page->id, 'en', ['meta_title' => 'Fallback']);
        $this->assertSame('Fallback', $after['meta_title']);
    }

    public function test_meta_is_clamped_by_character_count_not_display_width(): void
    {
        $page = $this->publishedPage('cjk-clamp');
        $resolver = app(SeoResolverService::class);

        // 300 full-width CJK chars: char count 300 > 255. Width-based truncation
        // (the old Str::limit) would cut to ~127; char-based cuts to exactly 255.
        $seo = $resolver->resolve('page', $page->id, 'en', [
            'meta_title' => str_repeat('中', 300),
            'meta_description' => str_repeat('あ', 800), // 800 <= 1000 → untouched
        ]);

        $this->assertSame(255, mb_strlen($seo['meta_title']));
        $this->assertSame(800, mb_strlen($seo['meta_description']));
    }
}
