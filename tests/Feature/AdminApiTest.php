<?php

namespace Tests\Feature;

use App\Models\PageTranslation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_api_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/v1/posts');
        $response->assertUnauthorized();
    }

    public function test_admin_can_create_post_via_api(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Admin',
            'login' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('superadmin');

        $token = $user->createToken('test-token')->plainTextToken;

        $payload = [
            'status' => 'draft',
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'API Post',
                    'slug' => 'api-post',
                    'content_html' => '<p>Generated via API</p>',
                ],
            ],
        ];

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/posts', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.translations.0.slug', 'api-post');
    }

    public function test_admin_api_normalizes_flat_page_blocks_into_structured_layout(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::query()->create([
            'name' => 'Admin',
            'login' => 'page_api_admin',
            'email' => 'page-api-admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('superadmin');

        $token = $user->createToken('page-api-token')->plainTextToken;

        $payload = [
            'status' => 'draft',
            'page_type' => 'landing',
            'translations' => [
                [
                    'locale' => 'ru',
                    'title' => 'API Page',
                    'slug' => 'api-page',
                    'content_blocks' => [
                        ['type' => 'heading', 'data' => ['text' => 'API heading']],
                        ['type' => 'rich_text', 'data' => ['html' => '<p>API body</p>']],
                    ],
                ],
            ],
        ];

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/pages', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.translations.0.slug', 'api-page');

        $translation = PageTranslation::query()->where('locale', 'ru')->firstOrFail();
        $this->assertSame('section', data_get($translation->content_blocks, '0.type'));
        $this->assertSame('heading', data_get($translation->content_blocks, '0.children.0.type'));
        $this->assertSame('rich_text', data_get($translation->content_blocks, '0.children.1.type'));
    }
}
