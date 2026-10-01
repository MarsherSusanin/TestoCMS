<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Models\User;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Services\PageContentService;
use App\Modules\Content\Services\PageTranslationNormalizer;
use App\Modules\Content\Services\PageWorkflowService;
use App\Modules\Content\Services\PostWorkflowService;
use App\Modules\Content\Services\SlugResolverService;
use App\Modules\Core\Services\SiteChromeSettingsService;
use App\Modules\Ops\Services\PublishSchedulerService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublishingAuditProbeTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require '/private/tmp/testocms-publishing-audit/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms.seed_demo_content' => false, 'cms.default_locale' => 'en', 'app.locale' => 'en']);
    }

    private function page(string $slug, string $status = 'published', $date = null): Page
    {
        $p = Page::create(['status' => $status, 'page_type' => 'landing', 'published_at' => $date ?? now()->subDay()]);
        PageTranslation::create(['page_id' => $p->id, 'locale' => 'en', 'slug' => $slug, 'title' => 'Audit '.$slug, 'content_blocks' => [], 'rendered_html' => '<p>Audit secret '.$slug.'</p>']);

        return $p;
    }

    private function makePost(string $slug, $date = null): Post
    {
        $p = Post::create(['status' => 'published', 'published_at' => $date ?? now()->subDay()]);
        PostTranslation::create(['post_id' => $p->id, 'locale' => 'en', 'slug' => $slug, 'title' => 'Audit '.$slug, 'content_html' => '<p>body</p>', 'content_plain' => 'body', 'excerpt' => 'body', 'content_format' => 'html']);

        return $p;
    }

    public function test_embargo_bypass(): void
    {
        $this->page('future', date: now()->addDay());
        $this->makePost('future-post', now()->addDay());
        $this->get('/en/future')->assertOk()->assertSee('Audit secret future');
        $this->get('/en/blog/future-post')->assertOk();
        $this->getJson('/api/content/v1/pages/future?locale=en', ['X-API-Key' => 'test-content-key'])->assertOk();
        $this->getJson('/api/content/v1/posts/future-post?locale=en', ['X-API-Key' => 'test-content-key'])->assertOk();
        $this->get('/sitemaps/en.xml')->assertDontSee('/en/future');
    }

    public function test_schedule_unpublish_immediately_hides_content_and_keeps_cache(): void
    {
        $p = $this->page('retire');
        $this->get('/en/retire')->assertOk();
        app(PageWorkflowService::class)->schedule($p, 'unpublish', now()->addDay()->toDateTimeString(), Request::create('/', 'POST'));
        $this->assertSame('scheduled', $p->fresh()->status);
        $this->get('/en/retire')->assertOk()->assertHeader('X-TestoCMS-Cache', 'HIT');
        Cache::forget('cms:page-cache:keys');
        app(PageCacheService::class)->flushAll();
        Cache::flush();
        $this->get('/en/retire')->assertNotFound();
    }

    public function test_scheduler_keeps_stale_slug_status(): void
    {
        $p = $this->page('launch', 'scheduled');
        $this->get('/en/launch')->assertNotFound();
        PublishSchedule::create(['entity_type' => 'page', 'entity_id' => $p->id, 'action' => 'publish', 'due_at' => now()->subMinute()]);
        app(PublishSchedulerService::class)->runDue();
        $this->assertSame('published', $p->fresh()->status);
        $this->get('/en/launch')->assertNotFound();
        app(SlugResolverService::class)->flush('en', 'launch');
        $this->get('/en/launch')->assertOk();
    }

    public function test_scheduler_unpublish_leaves_public_html_after_flush(): void
    {
        $p = $this->page('remove');
        $this->get('/en/remove')->assertOk();
        PublishSchedule::create(['entity_type' => 'page', 'entity_id' => $p->id, 'action' => 'unpublish', 'due_at' => now()->subMinute()]);
        app(PublishSchedulerService::class)->runDue();
        $this->assertSame('draft', $p->fresh()->status);
        $this->get('/en/remove')->assertOk()->assertSee('Audit secret remove');
        app(SlugResolverService::class)->flush('en', 'remove');
        app(PageCacheService::class)->flushAll();
        $this->get('/en/remove')->assertNotFound();
    }

    public function test_preview_cached_beyond_expiration(): void
    {
        $p = $this->page('preview', 'draft');
        $t = PreviewToken::create(['entity_type' => 'page', 'entity_id' => $p->id, 'token' => str_repeat('a', 64), 'expires_at' => now()->addMinute()]);
        $url = '/preview/'.$t->token.'?locale=en';
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();
        $this->travel(2)->minutes();
        $this->get($url)->assertOk()->assertHeader('X-TestoCMS-Cache', 'HIT')->assertSee('Audit secret preview');
        Cache::flush();
        $this->get($url)->assertNotFound();
    }

    public function test_blog_query_pagination_repeats_page_one(): void
    {
        config(['cms.default_per_page' => 1]);
        $this->makePost('older', now()->subDays(2));
        $this->makePost('newer', now()->subDay());
        $one = $this->get('/en/blog')->assertOk();
        $one->assertSee('Audit newer')->assertDontSee('Audit older')->assertSee('?page=2');
        $this->get('/en/blog?page=2')->assertOk()->assertSee('Audit newer')->assertDontSee('Audit older');
        $this->get('/en/blog/page/2')->assertOk()->assertSee('Audit older');
    }

    public function test_rss_cdata_breaks_xml_and_inactive_category_feed_visible(): void
    {
        $p = $this->makePost('rss');
        $p->translations()->first()->update(['excerpt' => 'hello ]]> world']);
        $r = $this->get('/feed/en.xml')->assertOk();
        libxml_use_internal_errors(true);
        $this->assertFalse(simplexml_load_string($r->getContent()));
    }

    public function test_post_listing_persists_unpublished_post_even_after_cache_flush(): void
    {
        $post = $this->makePost('listing-secret');
        $blocks = [['type' => 'post_listing', 'data' => ['limit' => 10]]];
        $normal = app(PageTranslationNormalizer::class)->normalize([['locale' => 'en', 'title' => 'Listing', 'slug' => 'listing', 'content_blocks' => $blocks]], ['require_default_locale' => false]);
        $page = $this->page('listing');
        $page->translations()->first()->update(['content_blocks' => $blocks, 'rendered_html' => $normal['en']['rendered_html']]);
        $this->get('/en/listing')->assertSee('Audit listing-secret');
        app(PostWorkflowService::class)->unpublish($post, Request::create('/', 'POST'));
        $this->get('/en/listing')->assertOk()->assertSee('Audit listing-secret');
        $this->get('/en/blog/listing-secret')->assertNotFound();
    }

    public function test_replacement_schedule_retains_old_job(): void
    {
        $p = $this->page('pending', 'draft');
        $wf = app(PageWorkflowService::class);
        $wf->schedule($p, 'publish', now()->addHour()->toDateTimeString(), Request::create('/', 'POST'));
        $wf->schedule($p, 'publish', now()->addDays(2)->toDateTimeString(), Request::create('/', 'POST'));
        $this->assertSame(2, PublishSchedule::whereNull('executed_at')->count());
        $this->travel(2)->hours();
        app(PublishSchedulerService::class)->runDue();
        $this->assertSame('published', $p->fresh()->status);
    }

    public function test_locale_blog_is_empty_despite_total(): void
    {
        config(['cms.default_per_page' => 1]);
        $p = $this->makePost('russian-only');
        $p->translations()->first()->update(['locale' => 'ru']);
        $this->get('/en/blog')->assertOk()->assertSee('No published posts yet.')->assertSee('1 posts');
    }

    public function test_inactive_category_rss_remains_available(): void
    {
        $cat = Category::create(['is_active' => false]);
        CategoryTranslation::create(['category_id' => $cat->id, 'locale' => 'en', 'slug' => 'hidden', 'title' => 'Hidden']);
        $p = $this->makePost('hidden-category-post');
        $p->categories()->attach($cat->id);
        $this->get('/en/category/hidden')->assertNotFound();
        $this->get('/feed/en/category/hidden.xml')->assertOk()->assertSee('Audit hidden-category-post');
    }

    public function test_sqlite_search_ignores_body(): void
    {
        $p = $this->makePost('ordinary');
        $p->translations()->first()->update(['title' => 'Ordinary title', 'excerpt' => 'Ordinary excerpt', 'content_plain' => 'uniqueauditbodyword']);
        $chrome = app(SiteChromeSettingsService::class)->resolvedChrome();
        $this->get('/en/search?q=uniqueauditbodyword')->assertOk()->assertDontSee('Ordinary title');
    }

    public function test_language_switch_uses_same_slug_instead_of_translation(): void
    {
        $p = $this->page('english-slug');
        PageTranslation::create(['page_id' => $p->id, 'locale' => 'ru', 'slug' => 'russian-slug', 'title' => 'Russian title', 'content_blocks' => [], 'rendered_html' => '<p>RU</p>']);
        $this->get('/en/english-slug')->assertOk()->assertSee('http://localhost/ru/english-slug');
        $this->get('/ru/english-slug')->assertNotFound();
        $this->get('/ru/russian-slug')->assertOk();
    }

    public function test_removed_translation_still_served_from_slug_cache(): void
    {
        $user = User::create(['name' => 'Audit', 'email' => 'audit@example.test', 'login' => 'audit', 'password' => 'fake']);
        $p = $this->page('pruned-en');
        PageTranslation::create(['page_id' => $p->id, 'locale' => 'ru', 'slug' => 'pruned-ru', 'title' => 'RU', 'content_blocks' => [], 'rendered_html' => '<p>RU</p>']);
        $this->get('/en/pruned-en')->assertOk();
        app(PageContentService::class)->updateFromValidated($p, ['status' => 'published', 'page_type' => 'landing', 'translations' => [['locale' => 'ru', 'title' => 'RU', 'slug' => 'pruned-ru']]], $user, ['require_default_locale' => false]);
        $this->assertDatabaseMissing('page_translations', ['slug' => 'pruned-en']);
        $this->get('/en/pruned-en')->assertOk()->assertSee('Audit secret pruned-en');
        Cache::flush();
        $this->get('/en/pruned-en')->assertNotFound();
    }

    public function test_api_mismatched_etag_still_returns_304(): void
    {
        $this->page('etag');
        $one = $this->getJson('/api/content/v1/pages/etag?locale=en', ['X-API-Key' => 'test-content-key'])->assertOk();
        $this->getJson('/api/content/v1/pages/etag?locale=en', ['X-API-Key' => 'test-content-key', 'If-None-Match' => '"mismatched-etag"', 'If-Modified-Since' => $one->headers->get('Last-Modified')])->assertStatus(304);
    }

    public function test_html_cache_hit_loses_security_headers(): void
    {
        config(['security.csp.enabled' => true, 'security.csp.report_only' => false, 'security.csp.directives' => ["default-src 'self'"]]);
        $this->page('headers');
        $this->get('/en/headers')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy',"default-src 'self'");
        $this->get('/en/headers')->assertOk()->assertHeader('X-TestoCMS-Cache','HIT')->assertHeaderMissing('X-Content-Type-Options')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('X-Frame-Options');
    }
}
