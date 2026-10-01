<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\PublishSchedule;
use App\Models\User;
use App\Modules\Content\Services\PageContentService;
use App\Modules\Content\Services\PostContentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\TransientToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPublicationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public static function entityTypes(): array
    {
        return [['posts'], ['pages']];
    }

    private function user(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create(['name' => $role, 'login' => $role, 'email' => $role.'@audit.local', 'password' => 'password', 'status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function payload(string $status = 'draft'): array
    {
        return ['status' => $status, 'translations' => [['locale' => 'ru', 'title' => 'Audit', 'slug' => 'audit', 'content_html' => '<p>Audit body</p>', 'content_blocks' => [['type' => 'heading', 'data' => ['text' => 'Audit']]]]]];
    }

    #[DataProvider('entityTypes')]
    public function test_author_cannot_create_published_through_web_or_api(string $type): void
    {
        $author = $this->user('author');
        $payload = $this->payload('published');
        $this->actingAs($author)->post('/admin/'.$type, ['status' => 'published', 'translations' => ['ru' => $payload['translations'][0]]])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $token = $author->createToken('write-only', [$type.':write'])->plainTextToken;
        $this->withToken($token)->postJson('/api/admin/v1/'.$type, $payload)->assertForbidden();
        $this->assertDatabaseCount($type, 0);
    }

    #[DataProvider('entityTypes')]
    public function test_publish_scope_is_required_even_for_superadmin_create(string $type): void
    {
        $admin = $this->user('superadmin');
        $writeOnly = $admin->createToken('write-only', [$type.':write'])->plainTextToken;
        $this->withToken($writeOnly)->postJson('/api/admin/v1/'.$type, $this->payload('published'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $both = $admin->createToken('publisher', [$type.':write', $type.':publish'])->plainTextToken;
        $this->withToken($both)->postJson('/api/admin/v1/'.$type, $this->payload('published'))->assertCreated()->assertJsonPath('data.status', 'published');
    }

    #[DataProvider('entityTypes')]
    public function test_published_content_cannot_be_edited_unpublished_or_deleted_by_author(string $type): void
    {
        $admin = $this->user('superadmin');
        $this->actingAs($admin)->postJson('/api/admin/v1/'.$type, $this->payload('published'))->assertCreated();
        $entity = $type === 'pages' ? Page::firstOrFail() : Post::firstOrFail();
        $author = $this->user('author');
        $this->actingAs($author)->put('/admin/'.$type.'/'.$entity->id, ['translations' => ['ru' => $this->payload()['translations'][0]]])->assertForbidden();
        $this->actingAs($author)->delete('/admin/'.$type.'/'.$entity->id)->assertForbidden();
        $this->actingAs($author)->post('/admin/'.$type.'/'.$entity->id.'/unpublish')->assertForbidden();
        $this->actingAs($author)->post('/admin/'.$type.'/bulk', ['action' => 'delete', 'ids' => [$entity->id]])->assertRedirect();
        $this->assertDatabaseHas($type, ['id' => $entity->id, 'status' => 'published']);
        $view = $this->actingAs($author)->get('/admin/'.$type.'/'.$entity->id.'/edit')->assertOk();
        $view->assertSee('Только чтение')->assertDontSee('id="'.rtrim($type, 's').'-form"', false);
    }

    #[DataProvider('entityTypes')]
    public function test_write_only_token_cannot_modify_published_content(string $type): void
    {
        $admin = $this->user('superadmin');
        $publisher = $admin->createToken('publisher', ['*'])->plainTextToken;
        $created = $this->withToken($publisher)->postJson('/api/admin/v1/'.$type, $this->payload('published'))->assertCreated();
        $this->app['auth']->forgetGuards();
        $writer = $admin->createToken('writer', [$type.':write'])->plainTextToken;
        $this->withToken($writer)->patchJson('/api/admin/v1/'.$type.'/'.$created->json('data.id'), ['translations' => [['locale' => 'ru', 'title' => 'Changed']]])->assertForbidden();
        $this->assertDatabaseHas($type, ['id' => $created->json('data.id'), 'status' => 'published']);
    }

    public function test_author_form_hides_published_and_schedule_actions(): void
    {
        $this->actingAs($this->user('author'))->get('/admin/posts/create')->assertOk()->assertDontSee('value="published"', false)->assertDontSee('value="scheduled"', false);
    }

    #[DataProvider('entityTypes')]
    public function test_write_only_token_cannot_unpublish_or_delete_published_content(string $type): void
    {
        $admin = $this->user('superadmin');
        $publisher = $admin->createToken('publisher', ['*'])->plainTextToken;
        $created = $this->withToken($publisher)->postJson('/api/admin/v1/'.$type, $this->payload('published'))->assertCreated();
        $this->app['auth']->forgetGuards();
        $writer = $admin->createToken('writer', [$type.':write'])->plainTextToken;
        $id = $created->json('data.id');
        $this->withToken($writer)->postJson('/api/admin/v1/'.$type.'/'.$id.'/unpublish')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($writer)->deleteJson('/api/admin/v1/'.$type.'/'.$id)->assertForbidden();
        $this->assertDatabaseHas($type, ['id' => $id, 'status' => 'published']);
    }

    #[DataProvider('entityTypes')]
    public function test_service_rechecks_status_after_controller_authorized_a_stale_draft(string $type): void
    {
        $author = $this->user('author');
        $class = $type === 'pages' ? PageContentService::class : PostContentService::class;
        $service = app($class);
        $stale = $service->createFromValidated($this->payload(), $author);
        Gate::forUser($author)->authorize('update', $stale);
        $stale->newQuery()->whereKey($stale->id)->update(['status' => 'published', 'published_at' => now()]);
        try {
            $service->updateFromValidated($stale, ['translations' => [['locale' => 'ru', 'title' => 'Unauthorized stale edit']]], $author, ['translation_mode' => 'merge', 'require_default_locale' => false]);
            $this->fail('Stale controller authorization must not permit editing a published material.');
        } catch (AuthorizationException $exception) {
            $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'ru', 'title' => 'Audit']);
        }
    }

    public function test_service_rechecks_user_permissions_after_role_changes(): void
    {
        $editor = $this->user('editor');
        $service = app(PostContentService::class);
        $draft = $service->createFromValidated($this->payload(), $editor);
        $authorized = $editor->fresh()->load('roles', 'permissions');
        Gate::forUser($authorized)->authorize('update', $draft);
        $editor->syncRoles('observer');
        $this->expectException(AuthorizationException::class);
        $service->updateFromValidated($draft, ['translations' => [['locale' => 'ru', 'title' => 'Forbidden']]], $authorized, ['translation_mode' => 'merge', 'require_default_locale' => false]);
    }

    #[DataProvider('entityTypes')]
    public function test_direct_status_cancellation_requires_publish_permission_and_scope(string $type): void
    {
        $admin = $this->user('superadmin');
        $service = app($type === 'pages' ? PageContentService::class : PostContentService::class);
        $entity = $service->createFromValidated($this->payload(), $admin);
        $entity->update(['status' => 'scheduled']);
        $schedule = PublishSchedule::create(['entity_type' => rtrim($type, 's'), 'entity_id' => $entity->id, 'action' => 'publish', 'due_at' => now()->addDay(), 'created_by' => $admin->id]);
        $author = $this->user('author');
        $authorToken = $author->createToken('writer', [$type.':write'])->plainTextToken;
        $url = '/api/admin/v1/'.$type.'/'.$entity->id;

        $this->withToken($authorToken)->patchJson($url, ['translations' => [['locale' => 'ru', 'title' => 'Allowed scheduled edit']]])->assertOk();
        $this->assertNull($schedule->fresh()->cancelled_at);
        $this->app['auth']->forgetGuards();
        $this->withToken($authorToken)->patchJson($url, ['status' => 'draft'])->assertForbidden();
        $this->assertDatabaseHas($type, ['id' => $entity->id, 'status' => 'scheduled']);
        $this->assertNull($schedule->fresh()->cancelled_at);

        $this->app['auth']->forgetGuards();
        $writer = $admin->createToken('write-only', [$type.':write'])->plainTextToken;
        $this->withToken($writer)->patchJson($url, ['status' => 'draft'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $publisher = $admin->createToken('publisher', [$type.':write', $type.':publish'])->plainTextToken;
        $this->withToken($publisher)->patchJson($url, ['status' => 'draft'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->assertNotNull($schedule->fresh()->cancelled_at);
    }

    #[DataProvider('entityTypes')]
    public function test_author_web_editor_preserves_existing_scheduled_status(string $type): void
    {
        $admin = $this->user('superadmin');
        $service = app($type === 'pages' ? PageContentService::class : PostContentService::class);
        $entity = $service->createFromValidated($this->payload(), $admin);
        $entity->update(['status' => 'scheduled']);
        $schedule = PublishSchedule::create(['entity_type' => rtrim($type, 's'), 'entity_id' => $entity->id, 'action' => 'publish', 'due_at' => now()->addDay(), 'created_by' => $admin->id]);
        $author = $this->user('author');
        $this->actingAs($author)->get('/admin/'.$type.'/'.$entity->id.'/edit')->assertOk()->assertSee('value="scheduled" selected', false)->assertDontSee('value="published"', false);
        $this->actingAs($author)->put('/admin/'.$type.'/'.$entity->id, ['status' => 'scheduled', 'translations' => ['ru' => array_replace($this->payload()['translations'][0], ['title' => 'Updated scheduled content'])]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas($type, ['id' => $entity->id, 'status' => 'scheduled']);
        $this->assertDatabaseHas(rtrim($type, 's').'_translations', ['locale' => 'ru', 'title' => 'Updated scheduled content']);
        $this->assertNull($schedule->fresh()->cancelled_at);
    }

    #[DataProvider('entityTypes')]
    public function test_sanctum_browser_transient_token_preserves_role_authorization(string $type): void
    {
        $admin = $this->user('superadmin')->withAccessToken(new TransientToken);
        $service = app($type === 'pages' ? PageContentService::class : PostContentService::class);
        $published = $service->createFromValidated($this->payload('published'), $admin);
        $this->assertSame('published', $published->status);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $author = $this->user('author')->withAccessToken(new TransientToken);
        $this->expectException(AuthorizationException::class);
        $service->createFromValidated($this->payload('published'), $author);
    }
}
