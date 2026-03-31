<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_shell_runtime_assets_require_authentication(): void
    {
        $this->get('/admin/runtime/admin-shell.js')->assertRedirect('/login');
        $this->get('/admin/runtime/admin-i18n.js')->assertRedirect('/login');
    }

    public function test_authenticated_superadmin_can_access_admin_shell_runtime_assets(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('shell-runtime@testocms.local', 'superadmin');

        foreach ([
            '/admin/runtime/admin-shell.js',
            '/admin/runtime/admin-i18n.js',
        ] as $path) {
            $response = $this->actingAs($superadmin)->get($path);
            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
        }
    }

    public function test_admin_dashboard_uses_shell_boot_payload_and_external_shell_runtimes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('shell-dashboard@testocms.local', 'superadmin');

        ThemeSetting::query()->updateOrCreate(
            ['key' => 'core_update_state'],
            ['settings' => [
                'available_release' => ['version' => '9.9.9'],
                'installed_version' => '1.0.0',
            ]]
        );

        $response = $this->actingAs($superadmin)->get('/admin');

        $response->assertOk()
            ->assertSee('id="testocms-admin-shell-boot"', false)
            ->assertSee('/admin/runtime/admin-shell.js', false)
            ->assertSee('/admin/runtime/admin-i18n.js', false)
            ->assertSee('data-nav-icon="layout-dashboard"', false)
            ->assertSee('9.9.9', false)
            ->assertDontSee("const key = 'testocms_admin_sidebar_collapsed';", false)
            ->assertDontSee('window.alert =', false)
            ->assertDontSee('window.confirm =', false);
    }

    public function test_sidebar_falls_back_to_short_label_when_icon_is_unknown(): void
    {
        $html = view('admin.partials.sidebar', [
            'adminShell' => [
                'nav' => [
                    'main' => [[
                        'href' => '/admin/export',
                        'label' => 'Экспорт',
                        'short_ru' => 'Э',
                        'icon' => 'unknown-glyph',
                    ]],
                    'extensions' => [],
                    'public' => [],
                ],
                'is_admin_ui_en' => false,
            ],
        ])->render();

        $normalizedHtml = preg_replace('/\s+/', '', $html) ?? $html;

        $this->assertStringContainsString('aria-hidden="true">Э</span>', $normalizedHtml);
        $this->assertStringNotContainsString('data-nav-icon="unknown-glyph"', $html);
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
