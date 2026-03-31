<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminEditorRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_requires_authentication(): void
    {
        $this->get('/admin/runtime/page-form.js')->assertRedirect('/login');
    }

    public function test_authenticated_superadmin_can_access_editor_runtime_assets(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('runtime-superadmin@testocms.local', 'superadmin');

        foreach ([
            '/admin/runtime/editor-shared.js',
            '/admin/runtime/asset-selector.js',
            '/admin/runtime/page-form.js',
            '/admin/runtime/page-fullscreen.js',
            '/admin/runtime/post-form.js',
        ] as $path) {
            $response = $this->actingAs($superadmin)->get($path);
            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
        }
    }

    public function test_page_create_uses_boot_payload_and_external_runtimes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('runtime-pages@testocms.local', 'superadmin');

        $response = $this->actingAs($superadmin)->get('/admin/pages/create');

        $response->assertOk()
            ->assertSee('id="testocms-page-editor-boot"', false)
            ->assertSee('/admin/runtime/editor-shared.js', false)
            ->assertSee('/admin/runtime/page-form.js', false)
            ->assertSee('/admin/runtime/page-fullscreen.js', false)
            ->assertDontSee("const form = document.getElementById('page-form');", false);
    }

    public function test_page_form_runtime_includes_structured_layout_walker(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('runtime-pages-walker@testocms.local', 'superadmin');

        $this->actingAs($superadmin)
            ->get('/admin/runtime/page-form.js')
            ->assertOk()
            ->assertSee('const walkNodes = (nodes, cb) => {', false)
            ->assertSee('walkNodes(state.rawNodes, (node) => {', false);
    }

    public function test_page_fullscreen_runtime_keeps_manual_open_available_on_narrow_viewports(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('runtime-pages-fullscreen@testocms.local', 'superadmin');

        $this->actingAs($superadmin)
            ->get('/admin/runtime/page-fullscreen.js')
            ->assertOk()
            ->assertSee('const shouldAutoOpenFullscreen = () => window.innerWidth >= 1024;', false)
            ->assertDontSee("dialogs.alert('Визуальный конструктор доступен на планшете и десктопе. На мобильном используйте встроенную форму страницы.');", false);
    }

    public function test_post_create_uses_boot_payload_and_external_runtime(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('runtime-posts@testocms.local', 'superadmin');

        $response = $this->actingAs($superadmin)->get('/admin/posts/create');

        $response->assertOk()
            ->assertSee('id="testocms-post-editor-boot"', false)
            ->assertSee('/admin/runtime/editor-shared.js', false)
            ->assertSee('/admin/runtime/asset-selector.js', false)
            ->assertSee('/admin/runtime/post-form.js', false)
            ->assertDontSee("const form = document.getElementById('post-form');", false);
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
