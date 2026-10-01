<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Models\User;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Caching\Services\PublicContentVersionService;
use App\Modules\Content\Services\PageContentService;
use App\Modules\Content\Services\PageWorkflowService;
use App\Modules\Content\Services\PostWorkflowService;
use App\Modules\Ops\Services\PublishSchedulerService;
use App\Modules\SEO\Services\SeoCacheKeys;
use Database\Seeders\RolesAndPermissionsSeeder;
use Dom\HTMLDocument;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms.seed_demo_content' => false, 'cms.default_locale' => 'en']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->publisher = User::create(['name' => 'Publisher', 'login' => 'publisher_safety', 'email' => 'publisher_safety@example.test', 'password' => 'password', 'status' => 'active']);
        $this->publisher->assignRole('superadmin');
    }

    private function actorRequest(): Request
    {
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->publisher);

        return $request;
    }

    private function page(string $slug, string $status = 'published'): Page
    {
        $page = Page::create(['status' => $status, 'page_type' => 'landing', 'published_at' => now()->subDay()]);
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'en', 'slug' => $slug, 'title' => 'Page '.$slug, 'content_blocks' => [], 'rendered_html' => '<p>Body '.$slug.'</p>']);

        return $page;
    }

    private function article(string $slug): Post
    {
        $post = Post::create(['status' => 'published', 'published_at' => now()->subDay()]);
        PostTranslation::create(['post_id' => $post->id, 'locale' => 'en', 'slug' => $slug, 'title' => 'Article '.$slug, 'content_format' => 'html', 'content_html' => '<p>Body</p>', 'content_plain' => 'Body', 'excerpt' => 'Excerpt '.$slug]);

        return $post;
    }

    public function test_page_editor_save_is_isolated_from_schedule_cancellation_form(): void
    {
        $this->assertEditorScheduleFormsAreIsolated('page');
    }

    public function test_post_editor_save_is_isolated_from_schedule_cancellation_form(): void
    {
        $this->assertEditorScheduleFormsAreIsolated('post');
    }

    private function assertEditorScheduleFormsAreIsolated(string $type): void
    {
        $entity = $type === 'page' ? $this->page('editor-schedule', 'draft') : $this->article('editor-schedule');
        $entity->update(['status' => 'draft']);
        $workflow = app($type === 'page' ? PageWorkflowService::class : PostWorkflowService::class);
        $job = $workflow->schedule($entity, 'publish', now()->addDay()->toIso8601String(), $this->actorRequest());
        $response = $this->actingAs($this->publisher)->get('/admin/'.$type.'s/'.$entity->id.'/edit')->assertOk();
        $legacyDocument = new \DOMDocument;
        $legacyDocument->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $documents = [$legacyDocument];
        if (class_exists(HTMLDocument::class)) {
            $documents[] = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
        }
        foreach ($documents as $document) {
            $editor = $document->getElementById($type.'-form');
            $this->assertNotNull($editor);
            $this->assertSame(0, $editor->getElementsByTagName('form')->length);
            $editorMethods = [];
            foreach ($editor->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('name') === '_method') {
                    $editorMethods[] = $input->getAttribute('value');
                }
            }
            $this->assertSame(['PUT'], $editorMethods);
            $cancelId = $type.'-schedule-cancel-'.$job->id;
            $cancel = $document->getElementById($cancelId);
            $this->assertNotNull($cancel);
            $this->assertSame('POST', strtoupper($cancel->getAttribute('method')));
            $this->assertSame(route('admin.'.$type.'s.schedules.cancel', [$entity, $job]), $cancel->getAttribute('action'));
            $cancelMethods = [];
            foreach ($cancel->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('name') === '_method') {
                    $cancelMethods[] = $input->getAttribute('value');
                }
            }
            $this->assertSame(['DELETE'], $cancelMethods);
            $this->assertNotSame($editor, $cancel->parentNode);
            $buttons = [];
            foreach ($editor->getElementsByTagName('button') as $button) {
                if ($button->getAttribute('form') === $cancelId) {
                    $buttons[] = $button;
                }
            }
            $this->assertCount(1, $buttons);
            $this->assertSame('submit', $buttons[0]->getAttribute('type'));
        }

        $translation = ['title' => 'Saved editor title', 'slug' => 'editor-schedule'];
        $translation[$type === 'page' ? 'blocks_json' : 'content_html'] = $type === 'page' ? '[{"type":"heading","data":{"text":"Saved body"}}]' : '<p>Saved body</p>';
        $this->post($editor->getAttribute('action'), ['_method' => $editorMethods[0], 'status' => 'draft', 'translations' => ['en' => $translation]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas($type.'s', ['id' => $entity->id, 'status' => 'draft']);
        $this->assertDatabaseHas($type.'_translations', [$type.'_id' => $entity->id, 'locale' => 'en', 'title' => 'Saved editor title']);
        $savedTranslation = $entity->translations()->where('locale', 'en')->firstOrFail();
        $this->assertStringContainsString('Saved body', $type === 'page' ? $savedTranslation->rendered_html : $savedTranslation->content_html);
        $this->assertNull($job->fresh()->cancelled_at);
        $this->assertNull($job->fresh()->executed_at);
    }

    public function test_future_and_null_dates_are_rejected_by_web_and_api(): void
    {
        foreach (['future' => now()->addDay(), 'undated' => null] as $slug => $date) {
            $this->page($slug)->update(['published_at' => $date]);
            $this->article($slug)->update(['published_at' => $date]);
            $this->get('/en/'.$slug)->assertNotFound();
            $this->get('/en/blog/'.$slug)->assertNotFound();
            $this->getJson('/api/content/v1/pages/'.$slug.'?locale=en&key=test-content-key')->assertNotFound();
            $this->getJson('/api/content/v1/posts/'.$slug.'?locale=en&key=test-content-key')->assertNotFound();
        }
    }

    public function test_warm_cache_rechecks_visibility_and_slug_cache_contains_only_identity(): void
    {
        $page = $this->page('warm');
        $this->get('/en/warm')->assertOk();
        $this->get('/en/warm')->assertHeader('X-TestoCMS-Cache', 'HIT');
        $version = app(PublicContentVersionService::class)->current();
        $this->assertSame(['type' => 'page', 'id' => $page->translations()->first()->id], Cache::get('cms:slug:'.PublicContentVersionService::CACHE_SCHEMA.':v'.$version.':en:warm'));
        DB::table('pages')->where('id', $page->id)->update(['status' => 'draft']);
        $this->get('/en/warm')->assertNotFound();
        $page->update(['status' => 'published']);
        $page->translations()->delete();
        $this->get('/en/warm')->assertNotFound();
    }

    public function test_scheduler_transitions_both_entities_and_rechecks_pending_jobs(): void
    {
        foreach (['page', 'post'] as $type) {
            $entity = $type === 'page' ? $this->page('scheduled-'.$type, 'draft') : $this->article('scheduled-'.$type);
            $entity->update(['status' => 'draft']);
            $path = '/en/'.($type === 'post' ? 'blog/' : '').'scheduled-'.$type;
            $this->get($path)->assertNotFound();
            $job = PublishSchedule::create(['entity_type' => $type, 'entity_id' => $entity->id, 'action' => 'publish', 'due_at' => now()->subMinute()]);
            $this->assertSame(1, app(PublishSchedulerService::class)->runDue());
            $this->get($path)->assertOk();
            $this->assertNotNull($job->fresh()->executed_at);
            $this->assertSame(0, app(PublishSchedulerService::class)->runDue());
            PublishSchedule::create(['entity_type' => $type, 'entity_id' => $entity->id, 'action' => 'unpublish', 'due_at' => now()->subMinute()]);
            $this->assertSame(1, app(PublishSchedulerService::class)->runDue());
            $this->get($path)->assertNotFound();
        }
    }

    public function test_upgrade_ignores_previous_html_slug_and_seo_cache_namespaces(): void
    {
        $page = $this->page('namespace');
        $other = $this->page('other-namespace');
        $version = app(PublicContentVersionService::class)->current();
        $request = Request::create('http://localhost/en/namespace');
        Cache::put('cms:page-cache:public-v2:g'.$version.':en:'.sha1($request->fullUrl()), ['content' => 'LEGACY DEMO HTML', 'status' => 200, 'headers' => ['Content-Type' => 'text/html']]);
        Cache::put('cms:slug:v'.$version.':en:namespace', ['type' => 'page', 'id' => $other->translations()->first()->id]);
        Cache::put('seo:sitemap:v'.$version.':en', 'LEGACY SITEMAP');

        $this->get('/en/namespace')->assertOk()->assertSee('Body')->assertDontSee('LEGACY DEMO HTML');
        $this->get('/sitemaps/en.xml')->assertOk()->assertDontSee('LEGACY SITEMAP');
        $this->assertSame($page->translations()->first()->id, Cache::get('cms:slug:'.PublicContentVersionService::CACHE_SCHEMA.':v'.$version.':en:namespace')['id']);
    }

    public function test_future_unpublish_remains_live_and_uses_new_seo_generation(): void
    {
        $page = $this->page('retire');
        $this->get('/en/retire')->assertOk();
        $this->get('/sitemaps/en.xml')->assertOk();
        $oldKey = SeoCacheKeys::sitemap('en');
        app(PageWorkflowService::class)->schedule($page, 'unpublish', now()->addHour()->toDateTimeString(), $this->actorRequest());
        $this->assertSame('published', $page->fresh()->status);
        $this->assertNotSame($oldKey, SeoCacheKeys::sitemap('en'));
        $this->get('/en/retire')->assertOk();
        $this->travel(2)->hours();
        app(PublishSchedulerService::class)->runDue();
        $this->get('/en/retire')->assertNotFound();
    }

    public function test_reschedule_preserves_cancelled_history_and_old_date_cannot_publish(): void
    {
        $page = $this->page('move', 'draft');
        $workflow = app(PageWorkflowService::class);
        $old = $workflow->schedule($page, 'publish', now()->addHour()->toDateTimeString(), $this->actorRequest());
        $new = $workflow->schedule($page, 'publish', now()->addDays(2)->toDateTimeString(), $this->actorRequest());
        $this->assertNotNull($old->fresh()->cancelled_at);
        $this->assertSame('rescheduled', $old->fresh()->cancellation_reason);
        $this->assertSame(1, PublishSchedule::pending()->count());
        $this->assertSame(2, PublishSchedule::count());
        $this->travel(2)->hours();
        $this->assertSame(0, app(PublishSchedulerService::class)->runDue());
        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNull($new->fresh()->executed_at);
    }

    public function test_draft_needs_publish_window_and_invalid_replacement_rolls_back(): void
    {
        $page = $this->page('window', 'draft');
        $workflow = app(PageWorkflowService::class);
        try {
            $workflow->schedule($page, 'unpublish', now()->addHours(3)->toDateTimeString(), $this->actorRequest());
            $this->fail('Hidden material must have an earlier publish.');
        } catch (ValidationException) {
            $this->assertSame(0, PublishSchedule::count());
        }
        $publish = $workflow->schedule($page, 'publish', now()->addHour()->toDateTimeString(), $this->actorRequest());
        $unpublish = $workflow->schedule($page, 'unpublish', now()->addHours(2)->toDateTimeString(), $this->actorRequest());
        $version = app(PublicContentVersionService::class)->current();
        try {
            $workflow->schedule($page, 'publish', now()->addHours(3)->toDateTimeString(), $this->actorRequest());
            $this->fail('Publish must precede unpublish.');
        } catch (ValidationException) {
            $this->assertNull($publish->fresh()->cancelled_at);
            $this->assertNull($unpublish->fresh()->cancelled_at);
            $this->assertSame($version, app(PublicContentVersionService::class)->current());
        }
    }

    public function test_manual_publish_retains_future_unpublish_and_manual_unpublish_cancels_both(): void
    {
        $post = $this->article('manual');
        $post->update(['status' => 'draft']);
        $workflow = app(PostWorkflowService::class);
        $publish = $workflow->schedule($post, 'publish', now()->addHour()->toDateTimeString(), $this->actorRequest());
        $unpublish = $workflow->schedule($post, 'unpublish', now()->addHours(2)->toDateTimeString(), $this->actorRequest());
        $workflow->publish($post, $this->actorRequest());
        $this->assertNotNull($publish->fresh()->cancelled_at);
        $this->assertNull($unpublish->fresh()->cancelled_at);
        $workflow->schedule($post, 'publish', now()->addMinutes(30)->toDateTimeString(), $this->actorRequest());
        $workflow->unpublish($post, $this->actorRequest());
        $this->assertSame(0, PublishSchedule::pending()->count());
        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_cancel_api_validates_membership_and_cancels_dependent_window(): void
    {
        $page = $this->page('api-cancel', 'draft');
        $other = $this->page('other', 'draft');
        $workflow = app(PageWorkflowService::class);
        $publish = $workflow->schedule($page, 'publish', now()->addHour()->toDateTimeString(), $this->actorRequest());
        $unpublish = $workflow->schedule($page, 'unpublish', now()->addHours(2)->toDateTimeString(), $this->actorRequest());
        $token = $this->publisher->createToken('management', ['pages:read', 'pages:publish'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/admin/v1/pages/'.$page->id)->assertOk()->assertJsonPath('data.scheduled_actions.0.id', $publish->id);
        $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/api/admin/v1/pages/'.$other->id.'/schedules/'.$publish->id)->assertNotFound();
        $this->assertNull($publish->fresh()->cancelled_at);
        $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/api/admin/v1/pages/'.$page->id.'/schedules/'.$publish->id)->assertOk();
        $this->assertSame('draft', $page->fresh()->status);
        $this->assertNotNull($publish->fresh()->cancelled_at);
        $this->assertNotNull($unpublish->fresh()->cancelled_at);
    }

    public function test_preview_is_no_store_and_expires_without_cache_hit(): void
    {
        $page = $this->page('preview', 'draft');
        $token = PreviewToken::create(['entity_type' => 'page', 'entity_id' => $page->id, 'token' => str_repeat('z', 64), 'expires_at' => now()->addMinute()]);
        $path = '/preview/'.$token->token.'?locale=en';
        $first = $this->get($path)->assertOk()->assertHeaderMissing('X-TestoCMS-Cache');
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $this->get($path)->assertOk()->assertHeaderMissing('X-TestoCMS-Cache');
        $this->travel(2)->minutes();
        $expired = $this->get($path)->assertNotFound();
        $this->assertStringContainsString('no-store', $expired->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $expired->headers->get('X-Robots-Tag'));
    }

    public function test_security_headers_survive_cache_hit(): void
    {
        config(['security.csp.enabled' => true, 'security.csp.report_only' => false, 'security.csp.directives' => ["default-src 'self'"]]);
        $this->page('headers');
        $this->get('/en/headers')->assertOk();
        $this->get('/en/headers')->assertOk()->assertHeader('X-TestoCMS-Cache', 'HIT')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Content-Security-Policy', "default-src 'self'");
    }

    public function test_nested_listing_is_live_in_html_api_and_actual_json_controls_etag(): void
    {
        $post = $this->article('listed');
        $page = $this->page('listing');
        $blocks = [['type' => 'section', 'children' => [['type' => 'columns', 'data' => ['columns' => [['span' => 12, 'children' => [['type' => 'post_listing', 'data' => ['limit' => 10]]]]]]]]]];
        $page->translations()->first()->update(['content_blocks' => $blocks, 'rendered_html' => '<p>Stale representation</p>']);
        $this->get('/en/listing')->assertOk()->assertSee('Article listed')->assertDontSee('Stale representation');
        $before = $this->getJson('/api/content/v1/pages/listing?locale=en&key=test-content-key')->assertOk();
        $this->assertStringContainsString('Article listed', $before->json('data.rendered_html'));
        $etag = $before->headers->get('ETag');
        app(PostWorkflowService::class)->unpublish($post, $this->actorRequest());
        $this->get('/en/listing')->assertOk()->assertDontSee('Article listed');
        $after = $this->getJson('/api/content/v1/pages/listing?locale=en&key=test-content-key', ['If-None-Match' => $etag])->assertOk();
        $this->assertStringNotContainsString('Article listed', $after->json('data.rendered_html'));
        $this->assertNotSame($etag, $after->headers->get('ETag'));
        $this->getJson('/api/content/v1/pages/listing?locale=en&key=test-content-key', ['If-None-Match' => $after->headers->get('ETag')])->assertStatus(304);
    }

    public function test_old_renderer_cannot_populate_new_generation_and_rollback_identity_is_never_reused(): void
    {
        $versions = app(PublicContentVersionService::class);
        $cache = app(PageCacheService::class);
        $request = Request::create('http://localhost/en/race');
        $cache->captureVersion($request);
        $oldKey = $cache->keyFromRequest($request);
        $before = $versions->current();
        DB::beginTransaction();
        $versions->bump();
        $rolledBack = $versions->current();
        DB::rollBack();
        $this->assertSame($before, $versions->current());
        $versions->bump();
        $this->assertNotSame($rolledBack, $versions->current());
        $cache->put($request, response('<p>Old HTML</p>', 200, ['Content-Type' => 'text/html']));
        $fresh = Request::create('http://localhost/en/race');
        $cache->captureVersion($fresh);
        $this->assertNotSame($oldKey, $cache->keyFromRequest($fresh));
        $this->assertNull($cache->get($fresh));
        $this->assertFalse(Cache::has($oldKey));
    }

    public function test_real_translation_replacement_hides_removed_cached_locale(): void
    {
        $page = $this->page('remove-en');
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'ru', 'title' => 'RU', 'slug' => 'keep-ru', 'content_blocks' => [], 'rendered_html' => '<p>RU</p>']);
        $this->get('/en/remove-en')->assertOk();
        app(PageContentService::class)->updateFromValidated($page, ['status' => 'published', 'translations' => [['locale' => 'ru', 'title' => 'RU', 'slug' => 'keep-ru']]], $this->publisher, ['require_default_locale' => false, 'translation_mode' => 'replace']);
        $this->get('/en/remove-en')->assertNotFound();
    }

    public function test_iso_schedule_is_normalized_to_server_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        $page = $this->page('timezone', 'draft');
        $due = now()->addHours(2)->setTimezone('Asia/Vladivostok');
        $job = app(PageWorkflowService::class)->schedule($page, 'publish', $due->toIso8601String(), $this->actorRequest());
        $this->assertSame($due->timestamp, $job->fresh()->due_at->timestamp);
    }

    public function test_late_seo_warmer_cannot_fill_the_new_generation(): void
    {
        $versions = app(PublicContentVersionService::class);
        $oldKey = SeoCacheKeys::sitemap('en');
        $oldXml = '<urlset><url><loc>/en/old</loc></url></urlset>';
        $versions->bump();
        // Simulate the callback of Cache::remember finishing after a committed edit.
        Cache::put($oldKey, $oldXml, 3600);
        $this->assertNotSame($oldKey, SeoCacheKeys::sitemap('en'));
        $this->assertFalse(Cache::has(SeoCacheKeys::sitemap('en')));
    }

    public function test_database_constraint_prevents_a_second_pending_task_of_same_action(): void
    {
        $page = $this->page('unique-pending', 'draft');
        PublishSchedule::create(['entity_type' => 'page', 'entity_id' => $page->id, 'action' => 'publish', 'due_at' => now()->addHour()]);
        try {
            DB::transaction(function () use ($page): void {
                PublishSchedule::create(['entity_type' => 'page', 'entity_id' => $page->id, 'action' => 'publish', 'due_at' => now()->addHours(2)]);
            });
            $this->fail('Pending slots must be unique even when bypassing the workflow.');
        } catch (QueryException) {
            $this->assertSame(1, PublishSchedule::pending()->count());
        }
    }
}
