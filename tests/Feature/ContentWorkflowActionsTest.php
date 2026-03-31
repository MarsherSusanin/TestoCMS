<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\PublishSchedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContentWorkflowActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_workflow_actions_keep_web_and_api_side_effects_consistent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('page-workflow@testocms.local', 'superadmin');
        $page = $this->createPage($superadmin, 'workflow-page');

        $this->seedPageCache();
        app(\App\Modules\Content\Services\SlugResolverService::class)->resolve('ru', 'workflow-page');
        $this->assertNotNull(Cache::get('cms:slug:ru:workflow-page'));

        $this->actingAs($superadmin)
            ->post('/admin/pages/'.$page->id.'/publish')
            ->assertRedirect();

        $this->assertSame('published', $page->fresh()->status);
        $this->assertNotNull($page->fresh()->published_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.publish.web', 'entity_id' => $page->id]);
        $this->assertNull(Cache::get('cms:page-cache:test'));
        $this->assertNull(Cache::get('cms:slug:ru:workflow-page'));

        $token = $superadmin->createToken('page-workflow-api', ['*'])->plainTextToken;
        $this->seedPageCache();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/pages/'.$page->id.'/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertSame('draft', $page->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.unpublish', 'entity_id' => $page->id]);
        $this->assertNull(Cache::get('cms:page-cache:test'));

        $dueAt = now()->addHour()->toDateTimeString();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/pages/'.$page->id.'/schedule', [
                'action' => 'publish',
                'due_at' => $dueAt,
            ])
            ->assertCreated();

        $schedule = PublishSchedule::query()->where('entity_type', 'page')->where('entity_id', $page->id)->latest('id')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('scheduled', $page->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.schedule', 'entity_id' => $page->id]);

        $preview = $this->actingAs($superadmin)
            ->post('/admin/pages/'.$page->id.'/preview-token', ['locale' => 'ru']);
        $preview->assertRedirect();
        $preview->assertSessionHas('preview_link');
        $this->assertDatabaseHas('preview_tokens', ['entity_type' => 'page', 'entity_id' => $page->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.preview_token.create.web', 'entity_id' => $page->id]);

        $this->seedPageCache();
        $this->actingAs($superadmin)
            ->delete('/admin/pages/'.$page->id)
            ->assertRedirect('/admin/pages');

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.delete.web', 'entity_id' => $page->id]);
        $this->assertNull(Cache::get('cms:page-cache:test'));
    }

    public function test_post_workflow_actions_keep_web_and_api_side_effects_consistent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('post-workflow@testocms.local', 'superadmin');
        $post = $this->createPost($superadmin, 'workflow-post');

        $this->seedPageCache();
        app(\App\Modules\Content\Services\SlugResolverService::class)->resolve('ru', 'blog/workflow-post');
        $this->assertNotNull(Cache::get('cms:slug:ru:blog/workflow-post'));

        $this->actingAs($superadmin)
            ->post('/admin/posts/'.$post->id.'/publish')
            ->assertRedirect();

        $this->assertSame('published', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.publish.web', 'entity_id' => $post->id]);
        $this->assertNull(Cache::get('cms:page-cache:test'));
        $this->assertNull(Cache::get('cms:slug:ru:blog/workflow-post'));

        $token = $superadmin->createToken('post-workflow-api', ['*'])->plainTextToken;
        $this->seedPageCache();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/posts/'.$post->id.'/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertSame('draft', $post->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.unpublish', 'entity_id' => $post->id]);
        $this->assertNull(Cache::get('cms:page-cache:test'));

        $dueAt = now()->addHour()->toDateTimeString();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/posts/'.$post->id.'/schedule', [
                'action' => 'publish',
                'due_at' => $dueAt,
            ])
            ->assertCreated();

        $schedule = PublishSchedule::query()->where('entity_type', 'post')->where('entity_id', $post->id)->latest('id')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('scheduled', $post->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.schedule', 'entity_id' => $post->id]);

        $preview = $this->actingAs($superadmin)
            ->post('/admin/posts/'.$post->id.'/preview-token', ['locale' => 'ru']);
        $preview->assertRedirect();
        $preview->assertSessionHas('preview_link');
        $this->assertDatabaseHas('preview_tokens', ['entity_type' => 'post', 'entity_id' => $post->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.preview_token.create.web', 'entity_id' => $post->id]);

        $this->seedPageCache();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/v1/posts/'.$post->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.delete', 'entity_id' => $post->id]);
        $this->assertNull(Cache::get('cms:page-cache:test'));
    }

    private function seedPageCache(): void
    {
        Cache::put('cms:page-cache:test', 'value', 60);
        Cache::forever('cms:page-cache:keys', ['cms:page-cache:test']);
    }

    private function createPage(User $author, string $slug): Page
    {
        $page = Page::query()->create([
            'author_id' => $author->id,
            'status' => 'draft',
            'page_type' => 'landing',
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'ru',
            'title' => 'Workflow page',
            'slug' => $slug,
            'content_blocks' => [['type' => 'section', 'children' => []]],
            'rendered_html' => '',
            'canonical_url' => '/ru/'.$slug,
        ]);

        return $page;
    }

    private function createPost(User $author, string $slug): Post
    {
        $post = Post::query()->create([
            'author_id' => $author->id,
            'status' => 'draft',
        ]);

        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'ru',
            'title' => 'Workflow post',
            'slug' => $slug,
            'content_format' => 'html',
            'content_html' => '<p>Workflow post</p>',
            'content_plain' => 'Workflow post',
            'canonical_url' => '/ru/blog/'.$slug,
        ]);

        return $post;
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
