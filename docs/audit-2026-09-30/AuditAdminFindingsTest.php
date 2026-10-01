<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Modules\Auth\Services\UserManagementService;
use App\Modules\Content\Services\PageContentService;
use App\Modules\Content\Services\PostContentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditAdminFindingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'superadmin'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::create(['name' => 'Audit', 'login' => 'audit_'.random_int(1, 999999), 'email' => random_int(1, 999999).'@audit.local', 'password' => 'password', 'status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    private function tr(string $locale = 'ru', string $slug = 'audit-post'): array
    {
        return ['locale' => $locale, 'title' => 'Audit Title', 'slug' => $slug, 'content_html' => '<p>Audit body</p>', 'content_blocks' => [['type' => 'heading', 'data' => ['text' => 'Audit heading']]]];
    }

    public function test_author_can_publish_post_via_status_web(): void
    {
        $u = $this->user('author');
        $this->assertFalse($u->can('posts:publish'));
        $this->actingAs($u)->post('/admin/posts', ['status' => 'published', 'translations' => ['ru' => $this->tr()]])->assertRedirect()->assertSessionHasNoErrors();
        $p = Post::firstOrFail();
        $this->assertSame('published', $p->status);
        $this->actingAs($u)->post('/admin/posts/'.$p->id.'/publish')->assertForbidden();
    }

    public function test_author_can_publish_page_via_status_web(): void
    {
        $u = $this->user('author');
        $this->assertFalse($u->can('pages:publish'));
        $this->actingAs($u)->post('/admin/pages', ['status' => 'published', 'page_type' => 'landing', 'translations' => ['ru' => array_merge($this->tr('ru', 'audit-page'), ['blocks_json' => '[]'])]])->assertRedirect()->assertSessionHasNoErrors();
        $p = Page::firstOrFail();
        $this->assertSame('published', $p->status);
        $this->actingAs($u)->post('/admin/pages/'.$p->id.'/publish')->assertForbidden();
    }

    public function test_write_only_token_can_publish_post_via_status(): void
    {
        $u = $this->user('author');
        $t = $u->createToken('audit', ['posts:write'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$t)->postJson('/api/admin/v1/posts', ['status' => 'published', 'translations' => [$this->tr()]])->assertCreated()->assertJsonPath('data.status', 'published');
    }

    public function test_write_only_token_can_publish_page_via_status(): void
    {
        $u = $this->user('author');
        $t = $u->createToken('audit', ['pages:write'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$t)->postJson('/api/admin/v1/pages', ['status' => 'published', 'page_type' => 'landing', 'translations' => [$this->tr('ru', 'audit-page')]])->assertCreated()->assertJsonPath('data.status', 'published');
    }

    public function test_blocked_user_keeps_session_access(): void
    {
        $u = $this->user('author');
        $this->actingAs($u)->get('/admin/posts')->assertOk();
        $admin = $this->user();
        app(UserManagementService::class)->updateStatus($admin, $u, 'blocked');
        $this->actingAs($u->fresh())->post('/admin/posts', ['status' => 'draft', 'translations' => ['ru' => $this->tr()]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('posts', ['author_id' => $u->id]);
    }

    public function test_blocked_user_keeps_bearer_token_access(): void
    {
        $u = $this->user('author');
        $t = $u->createToken('audit', ['posts:write'])->plainTextToken;
        app(UserManagementService::class)->updateStatus($this->user(), $u, 'blocked');
        $this->withHeader('Authorization', 'Bearer '.$t)->postJson('/api/admin/v1/posts', ['status' => 'draft', 'translations' => [$this->tr()]])->assertCreated();
    }

    public function test_patch_post_one_translation_deletes_other_locale(): void
    {
        $u = $this->user();
        $t = $u->createToken('audit')->plainTextToken;
        $r = $this->withHeader('Authorization', 'Bearer '.$t)->postJson('/api/admin/v1/posts', ['translations' => [$this->tr('ru'), $this->tr('en')]])->assertCreated();
        $id = $r->json('data.id');
        $this->assertDatabaseHas('post_translations', ['post_id' => $id, 'locale' => 'en']);
        $this->patchJson('/api/admin/v1/posts/'.$id, ['translations' => [$this->tr('ru')]])->assertOk();
        $this->assertDatabaseMissing('post_translations', ['post_id' => $id, 'locale' => 'en']);
    }

    public function test_patch_page_one_translation_deletes_other_locale(): void
    {
        $u = $this->user();
        $t = $u->createToken('audit')->plainTextToken;
        $r = $this->withHeader('Authorization', 'Bearer '.$t)->postJson('/api/admin/v1/pages', ['translations' => [$this->tr('ru', 'audit-page'), $this->tr('en', 'audit-page')]])->assertCreated();
        $id = $r->json('data.id');
        $this->assertDatabaseHas('page_translations', ['page_id' => $id, 'locale' => 'en']);
        $this->patchJson('/api/admin/v1/pages/'.$id, ['translations' => [$this->tr('ru', 'audit-page')]])->assertOk();
        $this->assertDatabaseMissing('page_translations', ['page_id' => $id, 'locale' => 'en']);
    }

    public function test_category_api_null_does_not_clear_parent_or_cover(): void
    {
        $u = $this->user();
        $t = $u->createToken('audit')->plainTextToken;
        $parent = Category::create(['is_active' => true]);
        $asset = Asset::create(['type' => 'image', 'disk' => 'public', 'storage_path' => 'audit.png', 'mime_type' => 'image/png', 'size' => 1]);
        $c = Category::create(['parent_id' => $parent->id, 'cover_asset_id' => $asset->id, 'is_active' => true]);
        $this->withHeader('Authorization', 'Bearer '.$t)->patchJson('/api/admin/v1/categories/'.$c->id, ['parent_id' => null, 'cover_asset_id' => null, 'translations' => [$this->tr()]])->assertOk()->assertJsonPath('data.parent_id', $parent->id)->assertJsonPath('data.cover_asset_id', $asset->id);
    }

    public function test_category_api_self_parent_and_invalid_slug_accepted(): void
    {
        $u = $this->user();
        $t = $u->createToken('audit')->plainTextToken;
        $c = Category::create(['is_active' => true]);
        $this->withHeader('Authorization', 'Bearer '.$t)->patchJson('/api/admin/v1/categories/'.$c->id, ['parent_id' => $c->id, 'translations' => [$this->tr('ru', 'blog')]])->assertOk()->assertJsonPath('data.parent_id', $c->id);
    }

    public function test_category_api_duplicate_slug_yields_500(): void
    {
        $u = $this->user();
        $t = $u->createToken('audit')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$t)->postJson('/api/admin/v1/categories', ['translations' => [$this->tr('ru', 'duplicate-category')]])->assertCreated();
        $this->postJson('/api/admin/v1/categories', ['translations' => [$this->tr('ru', 'duplicate-category')]])->assertStatus(500);
    }

    public function test_asset_api_delete_leaves_storage_file(): void
    {
        Storage::fake('public');
        $u = $this->user();
        $t = $u->createToken('audit')->plainTextToken;
        Storage::disk('public')->put('assets/audit.txt', 'audit');
        $a = Asset::create(['type' => 'document', 'disk' => 'public', 'storage_path' => 'assets/audit.txt', 'mime_type' => 'text/plain', 'size' => 5]);
        $this->withHeader('Authorization', 'Bearer '.$t)->deleteJson('/api/admin/v1/assets/'.$a->id)->assertNoContent();
        Storage::disk('public')->assertExists('assets/audit.txt');
        $this->assertDatabaseMissing('assets', ['id' => $a->id]);
    }

    public function test_category_web_descendant_can_be_parent_creating_cycle(): void
    {
        $u = $this->user();
        $a = Category::create(['is_active' => true]);
        $b = Category::create(['parent_id' => $a->id, 'is_active' => true]);
        $this->actingAs($u)->put('/admin/categories/'.$a->id, ['parent_id' => $b->id, 'is_active' => true, 'translations' => ['ru' => $this->tr('ru', 'cycle')]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($b->id, $a->fresh()->parent_id);
        $this->assertSame($a->id, $b->fresh()->parent_id);
    }

    public function test_category_web_blank_secondary_translation_is_not_deleted(): void
    {
        $u = $this->user();
        $c = Category::create(['is_active' => true]);
        foreach (['ru', 'en'] as $l) {
            CategoryTranslation::create(['category_id' => $c->id, 'locale' => $l, 'title' => 'Audit', 'slug' => 'audit-category']);
        }$this->actingAs($u)->put('/admin/categories/'.$c->id, ['is_active' => true, 'translations' => ['ru' => $this->tr('ru', 'audit-category'), 'en' => ['title' => '', 'slug' => '', 'description' => '']]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('category_translations', ['category_id' => $c->id, 'locale' => 'en', 'title' => 'Audit']);
    }

    public function test_post_api_update_drops_custom_head_html(): void
    {
        $u = $this->user();
        $p = app(PostContentService::class)->createFromValidated(['translations' => [array_merge($this->tr(), ['custom_head_html' => '<meta name="audit" content="one">'])]], $u, ['require_default_locale' => false]);
        $t = $u->createToken('audit')->plainTextToken;
        $this->assertStringContainsString('audit', $p->translations->first()->custom_head_html);
        $this->withHeader('Authorization', 'Bearer '.$t)->patchJson('/api/admin/v1/posts/'.$p->id, ['translations' => [array_merge($this->tr(), ['custom_head_html' => '<meta name="audit" content="two">'])]])->assertOk();
        $this->assertNull($p->fresh()->translations->first()->custom_head_html);
    }

    public function test_template_optional_description_omitted_yields_500(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/admin/templates', ['entity_type' => 'post', 'name' => 'Audit Template', 'payload_json' => json_encode(['entity' => [], 'translations' => ['ru' => $this->tr()]])])->assertStatus(500);
    }

    public function test_llm_accepts_unsupported_locale(): void
    {
        $u = $this->user();
        $t = $u->createToken('audit')->plainTextToken;
        config(['llm.providers.openai.api_key' => 'audit-fake-key']);
        Http::fake(['*' => Http::response(['output_text' => 'Audit generation'], 200)]);
        $this->withHeader('Authorization', 'Bearer '.$t)->postJson('/api/admin/v1/llm/generate-post', ['prompt' => 'Generate an audit post please', 'locale' => 'zz'])->assertCreated()->assertJsonPath('data.draft.translations.0.locale', 'zz');
        $this->assertDatabaseHas('post_translations', ['locale' => 'zz']);
    }

    public function test_llm_responses_output_array_is_saved_as_json_text(): void
    {
        $u = $this->user();
        $t = $u->createToken('audit')->plainTextToken;
        config(['llm.providers.openai.api_key' => 'audit-fake-key']);
        $fixture = ['id' => 'resp_audit', 'object' => 'response', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'The generated article']]]]];
        Http::fake(['*' => Http::response($fixture, 200)]);
        $r = $this->withHeader('Authorization', 'Bearer '.$t)->postJson('/api/admin/v1/llm/generate-post', ['prompt' => 'Generate an audit post please', 'locale' => 'ru'])->assertCreated();
        $this->assertSame(json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $r->json('data.draft.translations.0.content_plain'));
    }

    public function test_content_service_default_context_throws_missing_method(): void
    {
        $u = $this->user();
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('shouldRequireDefaultLocale');
        app(PageContentService::class)->createFromValidated(['translations' => [$this->tr()]],$u);
    }
}
