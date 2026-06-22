<?php

namespace Tests\Feature;

use App\Models\PageTranslation;
use App\Models\User;
use App\Modules\Content\Services\PageContentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/**
 * Regression guard for the stored-XSS sinks: per-translation custom_head_html
 * and the custom_code_embed / html_embed_restricted block types are restricted
 * to advanced roles. A page-writing editor (pages:write but not an advanced
 * role) must not be able to inject either.
 */
class CustomCodeRoleGateTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $login, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'login' => $login,
            'email' => $login.'@testocms.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function assertGate403(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected a 403 to be thrown by the custom-code gate.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_editor_cannot_inject_custom_head_html(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $editor = $this->user('gate_editor', 'editor');
        $service = app(PageContentService::class);

        $this->assertGate403(fn () => $service->createFromValidated([
            'status' => 'draft',
            'page_type' => 'landing',
            'translations' => [[
                'locale' => 'ru',
                'title' => 'Gate Page',
                'slug' => 'gate-page',
                'custom_head_html' => '<script>alert(1)</script>',
            ]],
        ], $editor, ['require_default_locale' => true]));

        $this->assertDatabaseMissing('page_translations', ['slug' => 'gate-page']);
    }

    public function test_editor_cannot_inject_custom_code_embed_block(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $editor = $this->user('gate_editor2', 'editor');
        $token = $editor->createToken('gate-test')->plainTextToken;

        // content_blocks are accepted on the admin API; the gate must still fire.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/pages', [
                'status' => 'draft',
                'page_type' => 'landing',
                'translations' => [[
                    'locale' => 'ru',
                    'title' => 'Gate Page 2',
                    'slug' => 'gate-page-2',
                    'content_blocks' => [
                        ['type' => 'custom_code_embed', 'data' => ['html' => '<script>alert(1)</script>']],
                    ],
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('page_translations', ['slug' => 'gate-page-2']);
    }

    public function test_advanced_role_may_set_custom_head_html(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->user('gate_admin', 'admin');
        $service = app(PageContentService::class);

        $service->createFromValidated([
            'status' => 'draft',
            'page_type' => 'landing',
            'translations' => [[
                'locale' => 'ru',
                'title' => 'Advanced Page',
                'slug' => 'advanced-page',
                'custom_head_html' => '<meta name="verify" content="ok">',
            ]],
        ], $admin, ['require_default_locale' => true]);

        $translation = PageTranslation::query()->where('slug', 'advanced-page')->firstOrFail();
        $this->assertStringContainsString('verify', (string) $translation->custom_head_html);
    }
}
