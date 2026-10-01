<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminTranslationPatchTest extends TestCase
{
    use RefreshDatabase;

    public static function entityTypes(): array
    {
        return [['posts'], ['pages']];
    }

    private function createContent(string $type): int
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create(['name' => 'Admin', 'login' => 'patch', 'email' => 'patch@audit.local', 'password' => 'password', 'status' => 'active']);
        $user->assignRole('superadmin');
        $this->withToken($user->createToken('patch', ['*'])->plainTextToken);
        $translations = [];
        foreach (['ru', 'en'] as $locale) {
            $translations[] = ['locale' => $locale, 'title' => 'Original '.$locale, 'slug' => 'patch-content', 'content_html' => '<p>Retained body</p>', 'content_blocks' => [['type' => 'heading', 'data' => ['text' => 'Retained body']]], 'meta_description' => 'Retained SEO', 'custom_head_html' => '<meta name="verification" content="retained">'];
        }

        return $this->postJson('/api/admin/v1/'.$type, ['translations' => $translations])->assertCreated()->json('data.id');
    }

    #[DataProvider('entityTypes')]
    public function test_patch_merges_fields_and_preserves_other_locale_and_custom_head(string $type): void
    {
        $id = $this->createContent($type);
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['translations' => [['locale' => 'ru', 'title' => 'Changed']]])->assertOk();
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'en', 'title' => 'Original en']);
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'ru', 'title' => 'Changed', 'meta_description' => 'Retained SEO', 'custom_head_html' => '<meta name="verification" content="retained">']);
        $translation = DB::table(rtrim($type, 's').'_translations')->where('locale', 'ru')->first();
        $this->assertStringContainsString('Retained body', $type === 'posts' ? $translation->content_html : $translation->content_blocks);
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['status' => 'review'])->assertOk()->assertJsonPath('data.status', 'review');
        $this->assertDatabaseCount(rtrim($type, 's').'_translations', 2);
    }

    #[DataProvider('entityTypes')]
    public function test_explicit_removal_and_put_replacement_are_deliberate(string $type): void
    {
        $id = $this->createContent($type);
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['remove_translations' => ['en']])->assertOk();
        $this->assertDatabaseMissing(rtrim($type, 's').'_translations', ['locale' => 'en']);
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['remove_translations' => ['ru']])->assertUnprocessable();
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'ru']);
        $this->putJson('/api/admin/v1/'.$type.'/'.$id, ['translations' => [['locale' => 'en', 'title' => 'Replacement', 'slug' => 'replacement']]])->assertOk();
        $this->assertDatabaseMissing(rtrim($type, 's').'_translations', ['locale' => 'ru']);
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'en', 'title' => 'Replacement']);
    }

    #[DataProvider('entityTypes')]
    public function test_conflicting_or_invalid_patch_is_atomic(string $type): void
    {
        $id = $this->createContent($type);
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['remove_translations' => ['en'], 'translations' => [['locale' => 'en', 'title' => 'Changed']]])->assertUnprocessable();
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['translations' => [['locale' => 'zz', 'title' => 'Changed']]])->assertUnprocessable();
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['translations' => [['locale' => 'ru', 'title' => '']]])->assertUnprocessable();
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'en', 'title' => 'Original en']);
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'ru', 'title' => 'Original ru']);
    }

    #[DataProvider('entityTypes')]
    public function test_writer_patch_preserves_stored_head_without_granting_raw_code_permission(string $type): void
    {
        $id = $this->createContent($type);
        $author = User::create(['name' => 'Author', 'login' => 'patch_author', 'email' => 'patch_author@audit.local', 'password' => 'password']);
        $author->assignRole('author');
        $this->app['auth']->forgetGuards();
        $this->withToken($author->createToken('writer', [$type.':write'])->plainTextToken);
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['translations' => [['locale' => 'ru', 'title' => 'Safe edit']]])->assertOk();
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'ru', 'title' => 'Safe edit', 'custom_head_html' => '<meta name="verification" content="retained">']);
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['translations' => [['locale' => 'ru', 'custom_head_html' => '<script>window.audit=1</script>']]])->assertForbidden();
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'ru', 'custom_head_html' => '<meta name="verification" content="retained">']);
    }

    #[DataProvider('entityTypes')]
    public function test_new_locale_needs_identity_and_default_locale_removal_preserves_other_translation(string $type): void
    {
        $id = $this->createContent($type);
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['remove_translations' => ['en']])->assertOk();
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['translations' => [['locale' => 'en', 'meta_description' => 'New metadata']]])->assertUnprocessable();
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['translations' => [['locale' => 'en', 'title' => 'New EN', 'slug' => 'new-en', 'meta_description' => 'New metadata']]])->assertOk();
        $this->patchJson('/api/admin/v1/'.$type.'/'.$id, ['remove_translations' => ['ru']])->assertOk();
        $this->assertDatabaseMissing(rtrim($type, 's').'_translations', ['locale' => 'ru']);
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'en', 'title' => 'New EN', 'meta_description' => 'New metadata']);
    }

    public function test_markdown_format_switch_requires_body_but_markdown_metadata_patch_preserves_body(): void
    {
        $id = $this->createContent('posts');
        $this->patchJson('/api/admin/v1/posts/'.$id, ['translations' => [['locale' => 'ru', 'content_format' => 'markdown']]])->assertUnprocessable();
        $this->assertDatabaseHas('post_translations', ['locale' => 'ru', 'content_format' => 'html']);
        $this->patchJson('/api/admin/v1/posts/'.$id, ['translations' => [['locale' => 'ru', 'content_format' => 'markdown', 'content_markdown' => '## Retained Markdown']]])->assertOk();
        $this->patchJson('/api/admin/v1/posts/'.$id, ['translations' => [['locale' => 'ru', 'content_format' => 'markdown', 'meta_title' => 'Metadata only']]])->assertOk();
        $this->assertDatabaseHas('post_translations', ['locale' => 'ru', 'content_markdown' => '## Retained Markdown', 'meta_title' => 'Metadata only']);
    }
}
