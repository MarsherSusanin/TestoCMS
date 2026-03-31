<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiAccessModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_keys_page_requires_authentication(): void
    {
        $this->get('/admin/api-keys')
            ->assertRedirect('/login');
    }

    public function test_api_keys_page_forbidden_without_required_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $editor = $this->makeUser('editor@testocms.local', 'editor');

        $this->actingAs($editor)
            ->get('/admin/api-keys')
            ->assertForbidden();
    }

    public function test_superadmin_can_create_full_access_key_and_revoke_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin@testocms.local', 'superadmin');
        $owner = $this->makeUser('integration-owner@testocms.local', 'admin');

        $response = $this->actingAs($superAdmin)->post('/admin/api-keys', [
            'label' => 'CRM integration',
            'owner_user_id' => $owner->id,
            'surfaces' => ['admin', 'content'],
            'full_access' => 1,
        ]);

        $response->assertRedirect('/admin/api-keys');
        $response->assertSessionHas('api_key_created');

        /** @var PersonalAccessToken|null $createdToken */
        $createdToken = PersonalAccessToken::query()
            ->where('tokenable_id', $owner->id)
            ->where('tokenable_type', User::class)
            ->where('name', 'ext:CRM integration')
            ->first();

        $this->assertNotNull($createdToken);
        $this->assertSame(['*'], $createdToken->abilities);

        $plainToken = (string) $response->getSession()->get('api_key_created.plain_token');
        $this->assertNotSame('', $plainToken);

        $this->actingAs($superAdmin)
            ->delete('/admin/api-keys/'.$createdToken->id)
            ->assertRedirect('/admin/api-keys');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $createdToken->id,
        ]);

        auth()->logout();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/admin/v1/posts')
            ->assertUnauthorized();
    }

    public function test_custom_scopes_validation_rejects_unknown_scope(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin@testocms.local', 'superadmin');

        $response = $this->actingAs($superAdmin)->post('/admin/api-keys', [
            'label' => 'Broken key',
            'owner_user_id' => $superAdmin->id,
            'surfaces' => ['admin'],
            'full_access' => 0,
            'abilities' => ['unknown:scope'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('abilities');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'name' => 'ext:Broken key',
        ]);
    }

    public function test_content_api_accepts_managed_key_and_rejects_invalid_or_expired_key(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = $this->makeUser('owner@testocms.local', 'admin');

        $post = Post::query()->create([
            'status' => 'published',
            'published_at' => now(),
        ]);
        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Managed token post',
            'slug' => 'managed-token-post',
            'content_html' => '<p>Hello</p>',
            'content_plain' => 'Hello',
        ]);

        $contentToken = $owner->createToken('ext:Content read', ['content:read'])->plainTextToken;
        $this->getJson('/api/content/v1/posts?locale=en', ['X-API-Key' => $contentToken])
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'managed-token-post');
        $this->getJson('/api/content/v1/posts?locale=en', ['Authorization' => 'Bearer '.$contentToken])
            ->assertOk();

        $invalidScopeToken = $owner->createToken('ext:No content', ['posts:read'])->plainTextToken;
        $this->getJson('/api/content/v1/posts?locale=en', ['X-API-Key' => $invalidScopeToken])
            ->assertUnauthorized();

        $expiredToken = $owner->createToken('ext:Expired content', ['content:read'], now()->subMinute())->plainTextToken;
        $this->getJson('/api/content/v1/posts?locale=en', ['X-API-Key' => $expiredToken])
            ->assertUnauthorized();
    }

    public function test_full_access_token_does_not_bypass_owner_rbac(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $author = $this->makeUser('author@testocms.local', 'author');

        $post = Post::query()->create([
            'status' => 'draft',
        ]);

        $token = $author->createToken('ext:Author full access', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/posts/'.$post->id.'/publish')
            ->assertForbidden();
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'login' => explode('@', $email)[0],
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
