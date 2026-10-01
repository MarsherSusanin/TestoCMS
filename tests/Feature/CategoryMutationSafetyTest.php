<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\User;
use App\Modules\Content\Services\CategoryContentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryMutationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms.seed_demo_content' => false, 'cms.supported_locales' => ['ru', 'en'], 'cms.default_locale' => 'ru']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actor = User::create(['name' => 'Category admin', 'login' => 'category_admin', 'email' => 'category@audit.local', 'password' => 'password', 'status' => 'active']);
        $this->actor->assignRole('superadmin');
        $this->withToken($this->actor->createToken('categories', ['categories:write', 'categories:read'])->plainTextToken);
    }

    private function create(string $slug = 'topics'): Category
    {
        return app(CategoryContentService::class)->create(['translations' => [['locale' => 'ru', 'title' => 'Category', 'slug' => $slug], ['locale' => 'en', 'title' => 'EN', 'slug' => $slug]]], $this->actor);
    }

    public function test_duplicate_slug_is_validation_error_and_rolls_back_creation(): void
    {
        $this->create();
        $this->postJson('/api/admin/v1/categories', ['translations' => [['locale' => 'ru', 'title' => 'Duplicate', 'slug' => 'topics']]])->assertUnprocessable();
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('category_translations', 2);
    }

    public function test_patch_null_clears_references_and_metadata_without_losing_other_fields(): void
    {
        $parent = $this->create('parent');
        $category = $this->create();
        $asset = Asset::create(['disk' => 'public', 'storage_path' => 'assets/a.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $category->update(['parent_id' => $parent->id, 'cover_asset_id' => $asset->id]);
        $category->translations()->where('locale', 'ru')->update(['meta_description' => 'Old metadata']);
        $this->patchJson('/api/admin/v1/categories/'.$category->id, ['parent_id' => null, 'cover_asset_id' => null, 'is_active' => false, 'translations' => [['locale' => 'ru', 'meta_description' => null]]])->assertOk();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'parent_id' => null, 'cover_asset_id' => null, 'is_active' => false]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $category->id, 'locale' => 'ru', 'title' => 'Category', 'slug' => 'topics', 'meta_description' => null]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $category->id, 'locale' => 'en']);
    }

    public function test_put_replaces_locales_and_patch_removal_cannot_remove_last_or_conflict(): void
    {
        $category = $this->create();
        $url = '/api/admin/v1/categories/'.$category->id;
        $this->patchJson($url, ['remove_translations' => ['en'], 'translations' => [['locale' => 'en', 'title' => 'Changed']]])->assertUnprocessable();
        $this->patchJson($url, ['remove_translations' => ['en']])->assertOk();
        $this->assertDatabaseMissing('category_translations', ['category_id' => $category->id, 'locale' => 'en']);
        $this->patchJson($url, ['remove_translations' => ['ru']])->assertUnprocessable();
        $this->putJson($url, ['translations' => [['locale' => 'en', 'title' => 'Replacement', 'slug' => 'replacement']]])->assertOk();
        $this->assertDatabaseMissing('category_translations', ['category_id' => $category->id, 'locale' => 'ru']);
    }

    public function test_self_parent_descendant_parent_and_existing_cycle_are_rejected(): void
    {
        $a = $this->create('a');
        $b = $this->create('b');
        $c = $this->create('c');
        $b->update(['parent_id' => $a->id]);
        $c->update(['parent_id' => $b->id]);
        $this->patchJson('/api/admin/v1/categories/'.$a->id, ['parent_id' => $a->id])->assertUnprocessable();
        $this->patchJson('/api/admin/v1/categories/'.$a->id, ['parent_id' => $c->id])->assertUnprocessable();
        $this->assertNull($a->fresh()->parent_id);
        $b->update(['parent_id' => $c->id]);
        $this->patchJson('/api/admin/v1/categories/'.$a->id, ['parent_id' => $b->id])->assertUnprocessable();
        $this->artisan('cms:categories:check')->expectsOutputToContain('Categories in cycles: '.$b->id.', '.$c->id)->assertExitCode(1);
        $this->assertSame($c->id, $b->fresh()->parent_id);
    }

    public function test_api_locale_identity_slug_rules_are_shared_with_web(): void
    {
        foreach ([['locale' => 'zz', 'title' => 'Unsupported', 'slug' => 'invalid'], ['locale' => 'ru', 'title' => 'Reserved', 'slug' => 'blog'], ['locale' => 'ru', 'title' => 'Nested', 'slug' => 'nested/path']] as $translation) {
            $this->postJson('/api/admin/v1/categories', ['translations' => [$translation]])->assertUnprocessable();
        }
        $category = $this->create();
        $this->patchJson('/api/admin/v1/categories/'.$category->id, ['translations' => [['locale' => 'en', 'title' => '']]])->assertUnprocessable();
        $this->postJson('/api/admin/v1/categories', ['translations' => [['locale' => 'ru', 'title' => 'One', 'slug' => 'one'], ['locale' => 'ru', 'title' => 'Two', 'slug' => 'two']]])->assertUnprocessable();
    }

    public function test_web_clear_optional_locale_removes_it_and_preserves_hidden_metadata(): void
    {
        $category = $this->create();
        $category->translations()->where('locale', 'ru')->update(['structured_data' => ['@type' => 'CollectionPage']]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->actor)->put('/admin/categories/'.$category->id, ['is_active' => true, 'translations' => ['ru' => ['title' => 'Category', 'slug' => 'topics'], 'en' => ['title' => '', 'slug' => '', 'description' => '']]])->assertRedirect();
        $this->assertDatabaseMissing('category_translations', ['category_id' => $category->id, 'locale' => 'en']);
        $this->assertSame(['@type' => 'CollectionPage'], $category->translations()->where('locale', 'ru')->firstOrFail()->structured_data);
    }

    public function test_delete_keeps_posts_and_promotes_children_to_roots(): void
    {
        $parent = $this->create('parent');
        $child = $this->create('child');
        $child->update(['parent_id' => $parent->id]);
        $this->deleteJson('/api/admin/v1/categories/'.$parent->id)->assertNoContent();
        $this->assertNull($child->fresh()->parent_id);
        $this->assertDatabaseMissing('category_translations', ['category_id' => $parent->id]);
    }

    public function test_real_unique_collision_after_preflight_is_422_and_rolls_back(): void
    {
        CategoryTranslation::creating(static function (CategoryTranslation $translation): void {
            // A real INSERT after slug preflight reproduces a competing writer's
            // late uniqueness conflict, without mocking a database exception.
            DB::table('category_translations')->insert($translation->getAttributes());
        });
        try {
            $this->postJson('/api/admin/v1/categories', ['translations' => [['locale' => 'ru', 'title' => 'Race', 'slug' => 'late-collision']]])->assertUnprocessable();
            $this->assertDatabaseCount('categories', 0);
            $this->assertDatabaseCount('category_translations', 0);
        } finally {
            CategoryTranslation::flushEventListeners();
        }
    }

    public function test_unrelated_primary_key_collision_is_not_masked_as_slug_validation(): void
    {
        $existing = $this->create('existing');
        $primaryKey = $existing->translations()->where('locale', 'ru')->firstOrFail()->id;
        CategoryTranslation::creating(static function (CategoryTranslation $translation) use ($primaryKey): void {
            $translation->id = $primaryKey;
        });
        try {
            try {
                app(CategoryContentService::class)->create(['translations' => [['locale' => 'en', 'title' => 'category_translations_locale_slug_unique', 'slug' => 'unrelated-primary']]], $this->actor);
                $this->fail('The unrelated primary key error must propagate.');
            } catch (UniqueConstraintViolationException $exception) {
                $message = $exception->getMessage();
                $this->assertTrue(str_contains($message, 'category_translations.id') || str_contains($message, 'PRIMARY') || str_contains($message, '_pkey'), $message);
            }
            $this->assertDatabaseCount('categories', 1);
            $this->assertDatabaseCount('category_translations', 2);
        } finally {
            CategoryTranslation::flushEventListeners();
        }
    }
}
