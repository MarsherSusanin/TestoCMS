<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveUser;
use App\Models\User;
use App\Modules\Auth\Services\UserManagementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserAccessRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create(['name' => $role, 'login' => $role, 'email' => $role.'@revoke.local', 'password' => 'password', 'status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_block_then_unblock_does_not_resurrect_existing_browser_session(): void
    {
        $author = $this->user('author');
        $admin = $this->user('superadmin');
        $this->post('/admin/login', ['email' => $author->email, 'password' => 'password'])->assertRedirect('/admin');
        $oldVersion = (int) $author->fresh()->auth_version;
        $this->assertSame($oldVersion, session(EnsureActiveUser::SESSION_VERSION));
        $token = $author->createToken('old', ['posts:read'])->plainTextToken;
        app(UserManagementService::class)->updateStatus($admin, $author, 'blocked');
        app(UserManagementService::class)->updateStatus($admin, $author, 'active');
        $this->assertGreaterThan($oldVersion, $author->fresh()->auth_version);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->get('/admin/posts')->assertRedirect('/admin/login');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/v1/posts')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->post('/admin/login', ['email' => $author->email, 'password' => 'password'])->assertRedirect('/admin');
    }

    public function test_direct_block_is_enforced_for_active_api_token(): void
    {
        $author = $this->user('author');
        $token = $author->createToken('unrevoked', ['posts:read'])->plainTextToken;
        $author->update(['status' => 'blocked']);
        $this->withToken($token)->getJson('/api/admin/v1/posts')->assertForbidden();
    }

    public function test_full_profile_block_revokes_tokens_and_remember_cookie(): void
    {
        $author = $this->user('author');
        $admin = $this->user('superadmin');
        $author->forceFill(['remember_token' => 'previous-remember-token'])->save();
        $author->createToken('old', ['posts:read']);
        $oldVersion = (int) $author->fresh()->auth_version;
        app(UserManagementService::class)->updateUser($admin, $author, ['status' => 'blocked'], ['author']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNotSame('previous-remember-token', $author->fresh()->remember_token);
        $this->assertGreaterThan($oldVersion, $author->fresh()->auth_version);
    }

    public function test_password_change_invalidates_sessions_for_non_database_driver(): void
    {
        config(['session.driver' => 'array']);
        $author = $this->user('author');
        $admin = $this->user('superadmin');
        $this->post('/admin/login', ['email' => $author->email, 'password' => 'password'])->assertRedirect('/admin');
        $author->createToken('old', ['posts:read']);
        app(UserManagementService::class)->changePassword($admin, $author, 'NewPassword123!');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->get('/admin/posts')->assertRedirect('/admin/login');
        $this->app['auth']->forgetGuards();
        $this->post('/admin/login', ['email' => $author->email, 'password' => 'NewPassword123!'])->assertRedirect('/admin');
    }

    public function test_legacy_session_without_version_requires_login(): void
    {
        $author = $this->user('author');
        $this->withSession([Auth::guard('web')->getName() => $author->id]);
        $this->app['auth']->forgetGuards();
        $this->get('/admin/posts')->assertRedirect('/admin/login');
    }

    public function test_remember_recall_validated_before_password_change_cannot_upgrade_its_session(): void
    {
        $author = $this->user('author');
        $admin = $this->user('superadmin');
        $author->forceFill(['remember_token' => 'previous-remember-token'])->save();
        $rememberedUser = $author->fresh();
        app(UserManagementService::class)->changePassword($admin, $author, 'NewPassword123!');
        $guard = Auth::guard('web');
        $guard->setUser($rememberedUser);
        $viaRemember = new \ReflectionProperty($guard, 'viaRemember');
        $viaRemember->setValue($guard, true);
        $session = app('session')->driver();
        $session->put($guard->getName(), $author->id);
        $request = Request::create('/admin/posts', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $rememberedUser);
        $response = app(EnsureActiveUser::class)->handle($request, fn () => response('Allowed'));
        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull($session->get(EnsureActiveUser::SESSION_VERSION));
    }
}
