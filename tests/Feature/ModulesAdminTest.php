<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModulesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_page_requires_authentication(): void
    {
        $this->get('/admin/modules')
            ->assertRedirect('/login');
    }

    public function test_modules_page_forbidden_for_user_without_settings_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('editor@testocms.local', 'editor');

        $this->actingAs($user)
            ->get('/admin/modules')
            ->assertForbidden();
    }

    public function test_superadmin_can_open_modules_page_and_docs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->makeUser('superadmin@testocms.local', 'superadmin');

        $this->actingAs($user)
            ->get('/admin/modules')
            ->assertOk()
            ->assertSee('Модули');

        $this->actingAs($user)
            ->get('/admin/modules/docs')
            ->assertOk()
            ->assertSee('Документация модулей')
            ->assertSee('modules-authoring.md')
            ->assertSee('<h1>TestoCMS Modules Authoring Guide</h1>', false)
            ->assertDontSee('&lt;h1&gt;TestoCMS Modules Authoring Guide&lt;/h1&gt;', false);
    }

    public function test_settings_write_user_cannot_install_modules(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // A non-superadmin who has been delegated settings:write may view the
        // modules page, but must NOT be able to install/activate code.
        $user = User::query()->create([
            'name' => 'Ops',
            'login' => 'ops',
            'email' => 'ops@testocms.local',
            'password' => Hash::make('password'),
        ]);
        $user->givePermissionTo('settings:write');

        $this->actingAs($user)
            ->get('/admin/modules')
            ->assertOk();

        $this->actingAs($user)
            ->post('/admin/modules/install-bundled/testocms--booking', ['activate_now' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('cms_modules', ['module_key' => 'testocms/booking']);
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'login' => str_replace('@testocms.local', '', $email),
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
