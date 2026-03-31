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
