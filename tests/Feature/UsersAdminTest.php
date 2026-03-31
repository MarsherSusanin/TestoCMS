<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_page_requires_authentication(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_login_page_bootstraps_default_admin_when_database_has_no_users(): void
    {
        $this->assertSame(0, User::query()->count());

        $response = $this->get('/admin/login');

        $response->assertOk();

        $admin = User::query()->where('email', 'admin@testocms.local')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->login);
        $this->assertSame('active', $admin->status);
        $this->assertTrue($admin->hasRole('superadmin'));
    }

    public function test_default_admin_can_log_in_without_manual_seed_after_database_reset(): void
    {
        $this->assertSame(0, User::query()->count());

        $response = $this->post('/admin/login', [
            'email' => 'admin@testocms.local',
            'password' => 'ChangeMe123!',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticated();

        $admin = User::query()->where('email', 'admin@testocms.local')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('superadmin'));
    }

    public function test_users_page_forbidden_without_users_manage_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $editor = $this->makeUser('editor@testocms.local', 'editor');

        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_cannot_manage_superadmin_or_assign_superadmin_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->makeUser('admin@testocms.local', 'admin');
        $superadmin = $this->makeUser('superadmin@testocms.local', 'superadmin');
        $target = $this->makeUser('author@testocms.local', 'author');

        $this->actingAs($admin)
            ->get('/admin/users/'.$superadmin->id.'/edit')
            ->assertForbidden();

        $response = $this->actingAs($admin)->put('/admin/users/'.$target->id, [
            'name' => 'Author Updated',
            'login' => 'author_updated',
            'email' => 'author-updated@gmail.com',
            'status' => 'active',
            'roles' => ['superadmin'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('roles');

        $target->refresh();
        $this->assertFalse($target->hasRole('superadmin'));
    }

    public function test_superadmin_can_create_user_and_change_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin@testocms.local', 'superadmin');

        $create = $this->actingAs($superadmin)->post('/admin/users', [
            'name' => 'New Admin',
            'login' => 'new_admin',
            'email' => 'new-admin@gmail.com',
            'status' => 'active',
            'roles' => ['admin'],
            'password' => 'AdminPass123!',
            'password_confirmation' => 'AdminPass123!',
        ]);

        $create->assertRedirect();
        $create->assertSessionHasNoErrors();

        $createdUser = User::query()->where('email', 'new-admin@gmail.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole('admin'));

        $passwordChange = $this->actingAs($superadmin)->post('/admin/users/'.$createdUser->id.'/password', [
            'password' => 'ChangedPass123!',
            'password_confirmation' => 'ChangedPass123!',
        ]);

        $passwordChange->assertRedirect('/admin/users/'.$createdUser->id.'/edit');
        $passwordChange->assertSessionHasNoErrors();

        $createdUser->refresh();
        $this->assertTrue(Hash::check('ChangedPass123!', (string) $createdUser->password));
        $this->assertFalse(Hash::check('AdminPass123!', (string) $createdUser->password));
    }

    public function test_blocked_user_cannot_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $blocked = $this->makeUser('blocked@testocms.local', 'admin', 'blocked', 'BlockedPass123!');

        $response = $this->post('/admin/login', [
            'email' => $blocked->email,
            'password' => 'BlockedPass123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_view_roles_but_cannot_update_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->makeUser('admin@testocms.local', 'admin');
        $editorRole = Role::query()->where('name', 'editor')->where('guard_name', 'web')->firstOrFail();

        $this->actingAs($admin)->get('/admin/users/roles')->assertOk();

        $this->actingAs($admin)->put('/admin/users/roles/'.$editorRole->id, [
            'permissions' => ['posts:read'],
        ])->assertForbidden();
    }

    public function test_superadmin_can_update_non_superadmin_role_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin@testocms.local', 'superadmin');
        $editorRole = Role::query()->where('name', 'editor')->where('guard_name', 'web')->firstOrFail();

        $response = $this->actingAs($superadmin)->put('/admin/users/roles/'.$editorRole->id, [
            'permissions' => ['posts:read', 'pages:read'],
        ]);

        $response->assertRedirect('/admin/users/roles/'.$editorRole->id.'/edit');
        $response->assertSessionHasNoErrors();

        $editorRole->refresh();
        $actual = $editorRole->permissions()->pluck('name')->sort()->values()->all();
        $this->assertSame(['pages:read', 'posts:read'], $actual);
    }

    public function test_cannot_block_or_downgrade_last_active_superadmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('superadmin@testocms.local', 'superadmin');

        $blockResponse = $this->actingAs($superadmin)->post('/admin/users/'.$superadmin->id.'/status', [
            'status' => 'blocked',
        ]);
        $blockResponse->assertRedirect();
        $blockResponse->assertSessionHasErrors('status');

        $demoteResponse = $this->actingAs($superadmin)->put('/admin/users/'.$superadmin->id, [
            'name' => $superadmin->name,
            'login' => $superadmin->login,
            'email' => $superadmin->email,
            'status' => 'active',
            'roles' => ['admin'],
        ]);
        $demoteResponse->assertRedirect();
        $demoteResponse->assertSessionHasErrors('roles');
    }

    private function makeUser(
        string $email,
        string $role,
        string $status = 'active',
        string $password = 'password'
    ): User {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'login' => str_replace(['@', '.'], '_', explode('@', $email)[0]).'_'.random_int(10, 999),
            'email' => $email,
            'password' => Hash::make($password),
            'status' => $status,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
