<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Concerns\BuildsCacheableResponses;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Contracts\PageContentServiceContract;
use App\Modules\Core\Services\SiteChromeSettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicContentCorrectnessTest extends TestCase
{
    // InnoDB FULLTEXT sees committed rows, as the CRUD -> public GET cycle does.
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms.seed_demo_content' => false, 'cms.default_locale' => 'en', 'cms.default_per_page' => 2]);
    }

    private function article(string $slug, string $locale = 'en', string $body = 'Body'): Post
    {
        $post = Post::create(['status' => 'published', 'published_at' => now()->subDay()]);
        PostTranslation::create(['post_id' => $post->id, 'locale' => $locale, 'title' => 'Article '.$slug, 'slug' => $slug, 'content_html' => '<p>'.$body.'</p>', 'content_plain' => $body, 'excerpt' => $body]);

        return $post;
    }

    private function page(string $slug, string $locale = 'en', array $blocks = [], string $html = '<p>Page body</p>'): Page
    {
        $page = Page::create(['status' => 'published', 'published_at' => now()->subDay(), 'page_type' => 'landing']);
        PageTranslation::create(['page_id' => $page->id, 'locale' => $locale, 'slug' => $slug, 'title' => 'Metadata '.$slug, 'content_blocks' => $blocks, 'rendered_html' => $html]);

        return $page;
    }

    private function category(string $slug, string $locale = 'en'): Category
    {
        $category = Category::create(['is_active' => true]);
        CategoryTranslation::create(['category_id' => $category->id, 'locale' => $locale, 'title' => 'Category '.$slug, 'slug' => $slug]);

        return $category;
    }

    public function test_blog_query_pagination_is_locale_scoped_and_legacy_urls_redirect(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->article('english-'.$i);
            $this->article('russian-'.$i, 'ru');
        }
        $first = $this->get('/en/blog')->assertOk();
        $first->assertSee('Article english-5')->assertDontSee('Article english-3')->assertDontSee('Article russian-5');
        $first->assertSee('http://localhost/en/blog?page=2', false);
        $second = $this->get('/en/blog?page=2')->assertOk();
        $second->assertSee('Article english-3')->assertSee('Article english-2')->assertDontSee('Article english-5');
        $second->assertSee('href="http://localhost/en/blog?page=2"', false);
        $this->get('/en/blog/page/2')->assertStatus(301)->assertRedirect('/en/blog?page=2');
        $this->get('/en/blog/page/1')->assertStatus(301)->assertRedirect('/en/blog');
        $this->get('/en/blog?page=2')->assertHeader('X-TestoCMS-Cache', 'HIT')->assertSee('Article english-3');
    }

    public function test_category_pagination_filters_locale_and_has_page_canonical_even_after_seo_cache_hit(): void
    {
        $category = $this->category('topics');
        for ($i = 1; $i <= 3; $i++) {
            $this->article('topic-'.$i)->categories()->attach($category);
            $this->article('foreign-'.$i, 'ru')->categories()->attach($category);
        }
        $this->get('/en/category/topics')->assertOk()->assertSee('Article topic-3')->assertDontSee('Article foreign-3');
        $this->get('/en/category/topics?page=2')->assertOk()->assertSee('Article topic-1')->assertDontSee('Article topic-3')->assertSee('href="http://localhost/en/category/topics?page=2"', false);
    }

    public function test_switcher_uses_available_translation_slug_and_drops_private_query(): void
    {
        $page = $this->page('guide');
        $this->get('/en/guide?token=private&signature=private')->assertOk()->assertDontSee('http://localhost/ru/guide', false);
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'ru', 'title' => 'Руководство', 'slug' => 'rukovodstvo', 'rendered_html' => '<p>Текст</p>', 'content_blocks' => []]);
        app(PageCacheService::class)->flushAll();
        $response = $this->get('/en/guide?token=private&signature=private')->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//a[contains(@href, "/ru/rukovodstvo")]');
        $this->assertGreaterThan(0, $links->length);
        foreach ($links as $link) {
            $this->assertSame('http://localhost/ru/rukovodstvo', $link->getAttribute('href'));
        }
        $page->update(['status' => 'draft']);
        $this->get('/ru/rukovodstvo')->assertNotFound();
    }

    public function test_search_and_blog_language_switches_reset_pagination_but_preserve_safe_search_fields(): void
    {
        $response = $this->get('/en/search?q=Body&type=posts&page=2&token=private')->assertOk();
        $response->assertSee('http://localhost/ru/search?q=Body&amp;type=posts', false)->assertDontSee('http://localhost/ru/search?q=Body&amp;type=posts&amp;page=2', false);
        $this->get('/en/blog?page=2&token=private')->assertOk()->assertSee('href="http://localhost/ru/blog"', false);
    }

    public function test_content_api_counts_only_requested_locale_for_pages_posts_and_categories(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->page('en-page-'.$i);
            $this->page('ru-page-'.$i, 'ru');
            $this->article('en-post-'.$i);
            $this->article('ru-post-'.$i, 'ru');
            $this->category('en-category-'.$i);
            $this->category('ru-category-'.$i, 'ru');
        }
        foreach (['pages', 'posts', 'categories'] as $type) {
            $this->getJson('/api/content/v1/'.$type.'?locale=ru&per_page=2&key=test-content-key')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data')->assertJsonPath('meta.locale', 'ru');
        }
        $this->getJson('/api/content/v1/pages/en-page-1?locale=ru&key=test-content-key')->assertNotFound();
    }

    public function test_post_api_categories_are_active_and_translated_in_requested_locale(): void
    {
        $post = $this->article('categorized');
        $active = $this->category('active');
        $foreign = $this->category('foreign', 'ru');
        $inactive = $this->category('inactive');
        $inactive->update(['is_active' => false]);
        $post->categories()->attach([$active->id, $foreign->id, $inactive->id]);
        $this->getJson('/api/content/v1/posts/categorized?locale=en&key=test-content-key')->assertOk()->assertJsonCount(1, 'data.categories')->assertJsonPath('data.categories.0.slug', 'active');
        $this->getJson('/api/content/v1/posts?locale=en&category=inactive&key=test-content-key')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_sqlite_search_matches_authored_unicode_body_and_literals(): void
    {
        $this->article('body-post', 'ru', 'УНИКАЛЬНЫЙЁЖ тест 100% готов');
        $this->page('body-page', 'ru', [['type' => 'rich_text', 'data' => ['html' => '<p>ПАРАГРАФ&nbsp;ЁЛКИ</p>']]], '<p>old snapshot</p>');
        $this->get('/ru/search?q=уникальныйёж')->assertOk()->assertSee('Article body-post');
        $this->get('/ru/search?q=параграф')->assertOk()->assertSee('Metadata body-page');
        $this->get('/ru/search?q=100%25')->assertOk()->assertSee('Article body-post')->assertDontSee('Metadata body-page');
        $this->get('/ru/search?q=zz%25')->assertOk()->assertDontSee('Article body-post')->assertDontSee('Metadata body-page');
        $this->get('/en/search?q=параграф')->assertOk()->assertDontSee('Metadata body-page');
    }

    public function test_page_search_ignores_nested_listing_snapshots_and_script_text(): void
    {
        $post = $this->article('leak-source', 'en', 'ListingSecretNeedle');
        $blocks = [['type' => 'section', 'children' => [['type' => 'columns', 'data' => ['columns' => [['children' => [
            ['type' => 'rich_text', 'data' => ['html' => '<p>OwnAuthoredNeedle</p>']],
            ['type' => 'post_listing', 'data' => ['limit' => 5]],
        ]]]]]]]];
        $this->page('listing-page', 'en', $blocks, '<p>ListingSecretNeedle</p><script>HiddenScriptNeedle</script>');
        $post->update(['status' => 'draft']);
        $this->get('/en/search?q=ListingSecretNeedle')->assertOk()->assertDontSee('Metadata listing-page');
        $this->get('/en/search?q=OwnAuthoredNeedle')->assertOk()->assertSee('Metadata listing-page')->assertDontSee('ListingSecretNeedle');
        $this->get('/en/search?q=HiddenScriptNeedle')->assertOk()->assertDontSee('Metadata listing-page');
    }

    public function test_projection_refreshes_on_author_edit_and_does_not_accept_client_projection(): void
    {
        $post = $this->article('projection-edit', 'en', 'PreviousNeedle');
        $translation = $post->translations()->firstOrFail();
        $translation->update(['content_html' => '<p>ReplacementNeedle</p>', 'excerpt' => '', 'search_text' => 'InjectedNeedle']);
        $this->get('/en/search?q=ReplacementNeedle')->assertOk()->assertSee('Article projection-edit');
        $this->get('/en/search?q=PreviousNeedle')->assertOk()->assertDontSee('Article projection-edit');
        $this->get('/en/search?q=InjectedNeedle')->assertOk()->assertDontSee('Article projection-edit');
    }

    public function test_projection_backfill_preserves_author_content_and_timestamps(): void
    {
        $page = $this->page('backfill', 'ru', [['type' => 'heading', 'data' => ['text' => 'НовыйКонтент']]], '<p>HistoricalSnapshot</p>');
        $translation = $page->translations()->firstOrFail();
        $before = DB::table('page_translations')->where('id', $translation->id)->first();
        $migration = require database_path('migrations/2026_10_02_000200_add_authored_search_projections.php');
        $migration->down();
        $migration->up();
        $after = DB::table('page_translations')->where('id', $translation->id)->first();
        foreach (['rendered_html', 'content_blocks', 'updated_at', 'created_at'] as $attribute) {
            $this->assertSame($before->{$attribute}, $after->{$attribute});
        }
        $this->assertStringContainsString('новыйконтент', $after->search_text);
        $this->assertStringNotContainsString('historicalsnapshot', $after->search_text);
    }

    public function test_rss_xml_round_trips_utf8_entities_cdata_terminators_and_excludes_inactive_categories(): void
    {
        $post = $this->article('xml-post', 'ru', 'Текст & <tag> ]]> конец');
        $post->translations()->firstOrFail()->update(['title' => 'Название & ]]> Ё', 'excerpt' => "Текст & <tag> ]]> конец\x01"]);
        $category = $this->category('rss-category', 'ru');
        $post->categories()->attach($category);
        $this->article('foreign-rss', 'en');
        $response = $this->get('/feed/ru.xml')->assertOk();
        $xml = new \DOMDocument;
        $this->assertTrue($xml->loadXML($response->getContent(), LIBXML_NONET));
        $this->assertSame(1, $xml->getElementsByTagName('item')->length);
        $this->assertSame('Название & ]]> Ё', $xml->getElementsByTagName('item')->item(0)->getElementsByTagName('title')->item(0)->textContent);
        $this->assertSame('Текст & <tag> ]]> конец', $xml->getElementsByTagName('description')->item(1)->textContent);
        $this->get('/feed/ru/category/rss-category.xml')->assertOk();
        $category->update(['is_active' => false]);
        $this->get('/feed/ru/category/rss-category.xml')->assertNotFound();
    }

    public function test_api_etag_hashes_actual_bytes_and_accepts_weak_list_and_wildcard(): void
    {
        $this->article('etag-post', 'ru', 'Ёж / path');
        $url = '/api/content/v1/posts/etag-post?locale=ru&key=test-content-key';
        $response = $this->getJson($url)->assertOk();
        $etag = $response->headers->get('ETag');
        $this->assertSame('"'.sha1($response->getContent()).'"', $etag);
        foreach ([$etag, 'W/'.$etag, '"other", W/'.$etag, '*'] as $header) {
            $this->getJson($url, ['If-None-Match' => $header])->assertStatus(304)->assertContent('')->assertHeader('ETag', $etag);
        }
        $this->getJson($url, ['If-None-Match' => 'junk'.$etag.'junk', 'If-Modified-Since' => gmdate(DATE_RFC7231, time() + 1000)])->assertOk();
    }

    public function test_etag_presence_precedes_date_even_for_empty_or_invalid_headers(): void
    {
        $responder = new class
        {
            use BuildsCacheableResponses;

            public function respond(Request $request): JsonResponse
            {
                return $this->cacheableJson($request, ['text' => 'Ёж /'], now()->subDay());
            }
        };
        foreach (['', '"mismatch"', 'malformed'] as $value) {
            $request = Request::create('/', 'GET', [], [], [], ['HTTP_IF_NONE_MATCH' => $value, 'HTTP_IF_MODIFIED_SINCE' => gmdate(DATE_RFC7231, time() + 1000)]);
            $this->assertSame(200, $responder->respond($request)->getStatusCode());
        }
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_IF_MODIFIED_SINCE' => gmdate(DATE_RFC7231, time() + 1000)]);
        $this->assertSame(304, $responder->respond($request)->getStatusCode());
    }

    public function test_public_page_is_builder_content_with_authored_h1_and_theme_chrome(): void
    {
        $chrome = app(SiteChromeSettingsService::class);
        $settings = $chrome->defaults();
        $settings['footer']['tagline_translations'] = ['en' => 'Configured footer', 'ru' => 'Настроенный подвал'];
        $chrome->save($settings);
        $this->page('builder-exact', 'en', [['type' => 'heading', 'data' => ['level' => 1, 'text' => 'Author heading']]], '<h1>Author heading</h1><p>Authored body</p>');
        $response = $this->get('/en/builder-exact')->assertOk();
        $response->assertSee('<h1>Author heading</h1>', false)->assertSee('Authored body')->assertSee('Configured footer')->assertDontSee('class="hero-panel"', false)->assertDontSee('class="hero-kpis"', false)->assertDontSee('<div class="meta-row">', false);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertSame(1, $document->getElementsByTagName('h1')->length);
        $this->assertNotNull($document->getElementsByTagName('header')->item(0));
        $this->assertNotNull($document->getElementsByTagName('footer')->item(0));
        $this->get('/en')->assertNotFound();
        $this->page('home', 'en', [], '<h1>Home authored</h1>');
        $this->get('/en')->assertOk()->assertSee('<h1>Home authored</h1>', false)->assertSee('href="http://localhost/en"', false)->assertDontSee('Included now');
    }

    public function test_stage_and_public_builder_scene_have_identical_authored_dom(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $actor = User::create(['name' => 'Builder', 'login' => 'pub_builder', 'email' => 'pub-builder@example.test', 'password' => 'password', 'status' => 'active']);
        $actor->assignRole('superadmin');
        $nodes = [['type' => 'section', 'data' => ['container' => 'wide', 'background' => 'brand-soft'], 'children' => [
            ['type' => 'heading', 'data' => ['level' => 1, 'text' => 'First authored H1']],
            ['type' => 'rich_text', 'data' => ['html' => '<p>Author body <strong>bold</strong></p>']],
        ]]];
        app(PageContentServiceContract::class)->createFromValidated([
            'status' => 'published', 'page_type' => 'landing',
            'translations' => [['locale' => 'en', 'title' => 'SEO title', 'slug' => 'scene-parity', 'content_blocks' => $nodes]],
        ], $actor);
        $public = $this->get('/en/scene-parity')->assertOk();
        $stage = $this->actingAs($actor)->post('/admin/pages/fullscreen-stage/render', [
            'locale' => 'en', 'page' => ['title' => 'SEO title', 'slug' => 'scene-parity'],
            'translations' => ['en' => ['nodes' => $nodes]], 'render' => ['instrument' => false],
        ])->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $scenes = [];
        foreach ([$public, $stage] as $response) {
            $document = new \DOMDocument;
            $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $scene = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " content-prose ")]')->item(0);
            $this->assertNotNull($scene);
            $html = '';
            foreach ($scene->childNodes as $child) {
                $html .= $document->saveHTML($child);
            }
            $scenes[] = trim($html);
            $this->assertSame(1, $document->getElementsByTagName('h1')->length);
            $this->assertNotNull($document->getElementsByTagName('header')->item(0));
            $this->assertNotNull($document->getElementsByTagName('footer')->item(0));
        }
        $this->assertSame($scenes[0], $scenes[1]);
    }

    public function test_api_translation_edit_with_unchanged_entity_timestamp_cannot_return_date_304(): void
    {
        $post = $this->article('translation-validator');
        $url = '/api/content/v1/posts/translation-validator?locale=en&key=test-content-key';
        $first = $this->getJson($url)->assertOk();
        $entityTimestamp = $post->updated_at->toDateTimeString();
        $post->translations()->firstOrFail()->update(['content_html' => '<p>Revised translation</p>', 'content_plain' => 'Revised translation']);
        $this->assertSame($entityTimestamp, $post->fresh()->updated_at->toDateTimeString());
        $second = $this->getJson($url, ['If-None-Match' => $first->headers->get('ETag'), 'If-Modified-Since' => gmdate(DATE_RFC7231, time() + 1000)])->assertOk()->assertJsonPath('data.content_plain', 'Revised translation');
        $this->assertNotSame($first->headers->get('ETag'), $second->headers->get('ETag'));
        $this->getJson($url, ['If-Modified-Since' => gmdate(DATE_RFC7231, time() + 1000)])->assertOk()->assertHeaderMissing('Last-Modified');
    }

    public function test_rss_filters_locale_before_fifty_item_limit_for_blog_and_category(): void
    {
        $category = $this->category('feed-limit', 'ru');
        foreach (['local-first', 'local-second'] as $slug) {
            $this->article($slug, 'ru')->categories()->attach($category);
        }
        for ($i = 0; $i < 51; $i++) {
            $this->article('foreign-limit-'.$i, 'en')->categories()->attach($category);
        }
        foreach (['/feed/ru.xml', '/feed/ru/category/feed-limit.xml'] as $url) {
            $response = $this->get($url)->assertOk();
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($response->getContent(), LIBXML_NONET));
            $this->assertSame(2, $document->getElementsByTagName('item')->length);
            $response->assertSee('Article local-first')->assertDontSee('foreign-limit');
        }
        $this->get('/feed/unknown.xml')->assertNotFound();
    }

    public function test_post_and_category_switcher_use_real_translated_slugs(): void
    {
        $post = $this->article('switch-post');
        PostTranslation::create(['post_id' => $post->id, 'locale' => 'ru', 'title' => 'Русский пост', 'slug' => 'russkiy-post', 'content_html' => '<p>Русский текст</p>']);
        $category = $this->category('switch-category');
        CategoryTranslation::create(['category_id' => $category->id, 'locale' => 'ru', 'title' => 'Русская рубрика', 'slug' => 'russkaya-rubrika']);
        foreach (['/en/blog/switch-post' => '/ru/blog/russkiy-post', '/en/category/switch-category' => '/ru/category/russkaya-rubrika'] as $url => $alternate) {
            $this->get($url.'?token=private')->assertOk()->assertSee('href="http://localhost'.$alternate.'"', false);
            $this->get($alternate)->assertOk();
        }
        $this->article('untranslated-post');
        $this->get('/en/blog/untranslated-post')->assertOk()->assertDontSee('href="http://localhost/ru/blog/untranslated-post"', false);
        $this->get('/ru/blog/untranslated-post')->assertNotFound();
    }
}
