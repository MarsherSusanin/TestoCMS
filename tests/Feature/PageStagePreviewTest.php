<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PageStagePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_preview_renders_canvas_without_service_meta_strip(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('stage-preview@testocms.local', 'superadmin');

        $response = $this->actingAs($superadmin)->post('/admin/pages/fullscreen-stage/render', [
            'locale' => 'en',
            'page' => [
                'title' => 'Preview page',
                'slug' => 'preview-page',
                'status' => 'draft',
                'page_type' => 'landing',
            ],
            'translations' => [
                'en' => [
                    'meta_title' => 'Preview meta',
                    'meta_description' => 'Preview description',
                    'canonical_url' => 'https://example.com/en/preview-page',
                    'custom_head_html' => '<meta name="preview" content="1">',
                    'nodes' => [
                        [
                            'type' => 'section',
                            'data' => [
                                'container' => 'boxed',
                                'padding_y' => 'md',
                                'background' => 'none',
                            ],
                            'children' => [
                                [
                                    'type' => 'heading',
                                    'data' => [
                                        'level' => 2,
                                        'text' => 'Preview heading',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertSee('data-builder-preview-root', false)
            ->assertDontSee('Stage Preview', false)
            ->assertDontSee('<div class="meta-row"', false);
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
