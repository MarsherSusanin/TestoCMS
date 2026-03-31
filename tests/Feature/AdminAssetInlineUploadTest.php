<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAssetInlineUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_upload_asset_via_admin_api_and_receive_picker_payload(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('asset-inline-upload@testocms.local', 'superadmin');
        $token = $superadmin->createToken('asset-upload', ['assets:write'])->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/admin/v1/assets', [
                'file' => UploadedFile::fake()->image('hero-image.png', 120, 80),
                'title' => 'Hero image',
                'alt' => 'Hero alt',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.title', 'Hero image')
            ->assertJsonPath('data.alt', 'Hero alt')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'mime_type',
                    'title',
                    'alt',
                    'caption',
                    'credits',
                    'disk',
                    'storage_path',
                    'public_url',
                    'size',
                    'width',
                    'height',
                ],
            ]);

        $asset = Asset::query()->latest('id')->firstOrFail();
        $this->assertSame('Hero image', $asset->title);
        $this->assertSame('Hero alt', $asset->alt);
        Storage::disk('public')->assertExists($asset->storage_path);
    }

    public function test_admin_asset_api_keeps_validation_failure_when_no_file_or_storage_path_is_provided(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('asset-inline-upload-missing@testocms.local', 'superadmin');
        $token = $superadmin->createToken('asset-upload-missing', ['assets:write'])->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/assets', [
                'title' => 'No file',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Either file upload or storage_path is required.');
    }

    public function test_post_create_form_renders_asset_selector_inline_upload_and_markdown_dropzone(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('asset-inline-post-form@testocms.local', 'superadmin');

        $response = $this->actingAs($superadmin)->get('/admin/posts/create');

        $response->assertOk()
            ->assertSee('data-media-picker-upload', false)
            ->assertSee('data-asset-selector', false)
            ->assertSee('name="featured_asset_id"', false)
            ->assertSee('data-markdown-import-zone=', false)
            ->assertSee('data-markdown-import-trigger=', false)
            ->assertSee('/admin/runtime/asset-selector.js', false);
    }

    public function test_category_create_form_renders_asset_selector_and_inline_media_upload(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('asset-inline-category-form@testocms.local', 'superadmin');

        $response = $this->actingAs($superadmin)->get('/admin/categories/create');

        $response->assertOk()
            ->assertSee('data-media-picker-upload', false)
            ->assertSee('data-asset-selector', false)
            ->assertSee('name="cover_asset_id"', false)
            ->assertSee('/admin/runtime/asset-selector.js', false);
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
