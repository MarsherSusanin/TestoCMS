<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * First dedicated coverage for the admin auth flow: the login gate is the
 * front door of the whole admin panel and had no tests at all.
 */
class AuthSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $status = 'active'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::query()->create([
            'name' => 'Admin', 'login' => 'auth_smoke_'.$status,
            'email' => 'auth_smoke_'.$status.'@testocms.local',
            'password' => Hash::make('secret-password'),
            'status' => $status,
        ]);
        $user->assignRole('superadmin');

        return $user;
    }

    public function test_guest_is_redirected_from_admin_to_login(): void
    {
        $this->get('/admin/pages')->assertRedirect();
        $this->assertGuest();
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = $this->makeUser();

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = $this->makeUser();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_blocked_account_cannot_log_in_even_with_valid_password(): void
    {
        $user = $this->makeUser('blocked');

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/admin/logout')->assertRedirect();
        $this->assertGuest();
    }
}
