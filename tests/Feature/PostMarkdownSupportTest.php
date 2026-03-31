<?php

namespace Tests\Feature;

use App\Models\ContentTemplate;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PostMarkdownSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_import_markdown_file_with_front_matter(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-md-import@testocms.local', 'superadmin');

        $markdown = <<<'MD'
---
title: Импортированный пост
slug: imported-post
excerpt: Краткий анонс
meta_title: SEO title
meta_description: SEO description
---
## Основной заголовок

Текст поста.
MD;

        $response = $this->actingAs($superadmin)->post('/admin/posts/markdown/import', [
            'locale' => 'ru',
            'markdown_file' => UploadedFile::fake()->createWithContent('post.md', $markdown),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Импортированный пост')
            ->assertJsonPath('data.slug', 'imported-post')
            ->assertJsonPath('data.content_format', 'markdown')
            ->assertJsonPath('data.excerpt', 'Краткий анонс');

        $this->assertStringContainsString('<h2>Основной заголовок</h2>', (string) $response->json('data.content_html'));
    }

    public function test_superadmin_can_store_markdown_post_and_reopen_editor_with_markdown_source(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-md-store@testocms.local', 'superadmin');
        $locale = strtolower((string) config('cms.default_locale', 'ru'));

        $response = $this->actingAs($superadmin)->post('/admin/posts', [
            'status' => 'draft',
            'translations' => [
                $locale => [
                    'title' => 'Markdown пост',
                    'slug' => 'markdown-post',
                    'content_format' => 'markdown',
                    'content_markdown' => "## Заголовок\n\nТекст **поста**.",
                    'content_html' => '',
                    'excerpt' => '',
                    'meta_title' => '',
                    'meta_description' => '',
                    'canonical_url' => '',
                    'custom_head_html' => '',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $post = Post::query()->latest('id')->firstOrFail();
        $translation = PostTranslation::query()->where('post_id', $post->id)->where('locale', $locale)->firstOrFail();

        $this->assertSame('markdown', $translation->content_format);
        $this->assertSame("## Заголовок\n\nТекст **поста**.", $translation->content_markdown);
        $this->assertStringContainsString('<h2>Заголовок</h2>', (string) $translation->content_html);
        $this->assertStringContainsString('Текст поста.', (string) $translation->content_plain);

        $edit = $this->actingAs($superadmin)->get('/admin/posts/'.$post->id.'/edit');
        $edit->assertOk()
            ->assertSee('value="markdown"', false)
            ->assertSee("## Заголовок\n\nТекст **поста**.", false);
    }

    public function test_admin_api_can_create_markdown_post(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-md-api@testocms.local', 'superadmin');
        $token = $superadmin->createToken('test-token', ['*'])->plainTextToken;
        $locale = strtolower((string) config('cms.default_locale', 'ru'));

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/posts', [
                'status' => 'draft',
                'translations' => [
                    [
                        'locale' => $locale,
                        'title' => 'API Markdown',
                        'slug' => 'api-markdown',
                        'content_format' => 'markdown',
                        'content_markdown' => "# API heading\n\nBody",
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $translation = PostTranslation::query()->where('slug', 'api-markdown')->firstOrFail();
        $this->assertSame('markdown', $translation->content_format);
        $this->assertSame("# API heading\n\nBody", $translation->content_markdown);
        $this->assertStringContainsString('<h1>API heading</h1>', (string) $translation->content_html);
    }

    public function test_bulk_duplicate_preserves_markdown_source(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-md-bulk@testocms.local', 'superadmin');

        $post = Post::query()->create([
            'author_id' => $superadmin->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'ru',
            'title' => 'Markdown source',
            'slug' => 'markdown-source',
            'content_format' => 'markdown',
            'content_markdown' => "## Исходник\n\nТело",
            'content_html' => '<h2>Исходник</h2><p>Тело</p>',
            'content_plain' => 'Исходник Тело',
            'canonical_url' => url('/ru/blog/markdown-source'),
        ]);

        $this->actingAs($superadmin)->post('/admin/posts/bulk', [
            'action' => 'duplicate',
            'ids' => [$post->id],
        ])->assertRedirect();

        $copy = Post::query()->where('id', '!=', $post->id)->latest('id')->firstOrFail();
        $copyTranslation = PostTranslation::query()->where('post_id', $copy->id)->where('locale', 'ru')->firstOrFail();

        $this->assertSame('draft', $copy->status);
        $this->assertSame('markdown', $copyTranslation->content_format);
        $this->assertSame("## Исходник\n\nТело", $copyTranslation->content_markdown);
        $this->assertSame('markdown-source-copy', $copyTranslation->slug);
    }

    public function test_post_template_prefill_preserves_markdown_mode(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin-md-template@testocms.local', 'superadmin');
        $locale = strtolower((string) config('cms.default_locale', 'ru'));

        $payload = [
            'entity' => [
                'featured_asset_id' => null,
                'category_ids' => [],
            ],
            'translations' => [
                $locale => [
                    'title' => 'Шаблон markdown поста',
                    'slug' => 'markdown-template',
                    'content_format' => 'markdown',
                    'content_html' => '<h2>Шаблон</h2><p>Контент</p>',
                    'content_markdown' => "## Шаблон\n\nКонтент",
                    'excerpt' => 'Описание',
                    'meta_title' => '',
                    'meta_description' => '',
                    'canonical_url' => '',
                    'custom_head_html' => '',
                ],
            ],
        ];

        $this->actingAs($superadmin)->post('/admin/templates', [
            'entity_type' => 'post',
            'name' => 'Markdown Template',
            'description' => 'Template',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ])->assertRedirect('/admin/templates?entity_type=post');

        $template = ContentTemplate::query()->where('entity_type', 'post')->firstOrFail();

        $response = $this->actingAs($superadmin)->get('/admin/posts/create?from_template='.$template->id);
        $response->assertOk()
            ->assertSee('Markdown Template')
            ->assertSee('value="markdown"', false)
            ->assertSee('markdown-template-copy')
            ->assertSee("## Шаблон\n\nКонтент", false);
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
