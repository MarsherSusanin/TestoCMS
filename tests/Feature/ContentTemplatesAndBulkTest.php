<?php

namespace Tests\Feature;

use App\Models\ContentTemplate;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Modules\Content\Services\ContentTemplateService;
use App\Modules\Content\Services\SlugUniquenessService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContentTemplatesAndBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_bulk_duplicate_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-bulk-pages@testocms.local', 'superadmin');

        $page = Page::query()->create([
            'author_id' => $superadmin->id,
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'ru',
            'title' => 'Offer',
            'slug' => 'offer',
            'content_blocks' => [['type' => 'heading', 'data' => ['text' => 'Offer']]],
            'rendered_html' => '<h1>Offer</h1>',
            'canonical_url' => url('/ru/offer'),
        ]);

        $response = $this->actingAs($superadmin)->post('/admin/pages/bulk', [
            'action' => 'duplicate',
            'ids' => [$page->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertSame(2, Page::query()->count());
        $duplicate = Page::query()->where('id', '!=', $page->id)->firstOrFail();
        $this->assertSame('draft', $duplicate->status);
        $duplicateTranslation = PageTranslation::query()->where('page_id', $duplicate->id)->where('locale', 'ru')->firstOrFail();
        $this->assertSame('offer-copy', $duplicateTranslation->slug);
        $this->assertSame('section', data_get($duplicateTranslation->content_blocks, '0.type'));
        $this->assertSame('heading', data_get($duplicateTranslation->content_blocks, '0.children.0.type'));
    }

    public function test_author_cannot_bulk_unpublish_pages_without_publish_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-pages-unpublish@testocms.local', 'superadmin');
        $author = $this->makeUser('author-pages-unpublish@testocms.local', 'author');

        $page = Page::query()->create([
            'author_id' => $superadmin->id,
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'ru',
            'title' => 'Offer',
            'slug' => 'offer-a',
            'content_blocks' => [],
            'rendered_html' => '',
        ]);

        $response = $this->actingAs($author)->post('/admin/pages/bulk', [
            'action' => 'unpublish',
            'ids' => [$page->id],
        ]);

        $response->assertRedirect();
        $page->refresh();
        $this->assertSame('published', $page->status);
    }

    public function test_superadmin_can_store_template_and_open_page_create_from_template(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-templates@testocms.local', 'superadmin');

        $payload = [
            'entity' => ['page_type' => 'landing'],
            'translations' => [
                'ru' => [
                    'title' => 'Шаблон оффера',
                    'slug' => 'offer',
                    'blocks_json' => json_encode([
                        ['type' => 'heading', 'data' => ['text' => 'Template heading']],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'rich_html' => '',
                    'meta_title' => 'Meta',
                    'meta_description' => 'Desc',
                    'canonical_url' => 'https://example.com/ru/offer',
                    'custom_head_html' => '',
                ],
            ],
        ];

        $store = $this->actingAs($superadmin)->post('/admin/templates', [
            'entity_type' => 'page',
            'name' => 'Landing Template',
            'description' => 'Demo template',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $store->assertRedirect('/admin/templates?entity_type=page');

        $template = ContentTemplate::query()->where('entity_type', 'page')->firstOrFail();
        $prefill = app(ContentTemplateService::class)->buildPrefillPayload($template, app(SlugUniquenessService::class));

        $create = $this->actingAs($superadmin)->get('/admin/pages/create?from_template='.$template->id);
        $create->assertOk();
        $create->assertSee('Landing Template');
        $create->assertSee('offer-copy');
        $create->assertDontSee('Fullscreen Layout v2');
        $create->assertDontSee('Legacy blocks');
        $this->assertSame('section', data_get(json_decode((string) data_get($prefill, 'translations.ru.blocks_json'), true), '0.type'));
    }

    public function test_pages_index_uses_action_submit_buttons_instead_of_nested_row_forms(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-pages-index@testocms.local', 'superadmin');

        $page = Page::query()->create([
            'author_id' => $superadmin->id,
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'ru',
            'title' => 'Главная',
            'slug' => 'home',
            'content_blocks' => [],
            'rendered_html' => '',
        ]);

        $response = $this->actingAs($superadmin)->get('/admin/pages');

        $response->assertOk();
        $response->assertSee('data-action-submit', false);
        $response->assertDontSee('class="action-menu-form"', false);
    }

    public function test_observer_cannot_store_page_template_without_write_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $observer = $this->makeUser('observer-templates@testocms.local', 'observer');

        $response = $this->actingAs($observer)->post('/admin/templates', [
            'entity_type' => 'page',
            'name' => 'Denied template',
            'description' => 'Should be forbidden',
            'payload_json' => json_encode(['entity' => [], 'translations' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, ContentTemplate::query()->count());
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'login' => str_replace(['@', '.'], '_', explode('@', $email)[0]).'_'.random_int(10, 999),
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
