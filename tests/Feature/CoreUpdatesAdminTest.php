<?php

namespace Tests\Feature;

use App\Models\CoreBackup;
use App\Models\ThemeSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class CoreUpdatesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_page_requires_authentication(): void
    {
        $this->get('/admin/updates')
            ->assertRedirect('/login');
    }

    public function test_updates_page_forbidden_without_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $editor = $this->makeUser('editor@testocms.local', 'editor');

        $this->actingAs($editor)
            ->get('/admin/updates')
            ->assertForbidden();
    }

    public function test_superadmin_can_check_updates_from_remote_manifest(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin@testocms.local', 'superadmin');

        config()->set('updates.server_url', 'https://updates.example.com');
        config()->set('updates.current_version', '1.0.0');

        Http::fake([
            'https://updates.example.com/api/updates/manifest*' => Http::response([
                'version' => '1.1.0',
                'channel' => 'stable',
                'requires' => [
                    'php' => '>=8.1',
                    'cms_from' => '1.0.0',
                ],
                'sha256' => str_repeat('a', 64),
                'signature' => '',
                'changelog_url' => 'https://updates.example.com/changelog/1.1.0',
                'min_migration_version' => '1.0.0',
            ], 200),
        ]);

        $this->actingAs($superAdmin)
            ->post('/admin/updates/check')
            ->assertRedirect('/admin/updates')
            ->assertSessionHas('status');

        $state = ThemeSetting::query()->where('key', 'core_update_state')->first();
        $this->assertNotNull($state);
        $this->assertSame('1.1.0', $state->settings['available_release']['version'] ?? null);

        $this->assertDatabaseHas('cms_core_update_logs', [
            'action' => 'check',
            'status' => 'success',
            'to_version' => '1.1.0',
        ]);
    }

    public function test_superadmin_can_upload_manual_release_zip(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin2@testocms.local', 'superadmin');

        $zipPath = $this->makeReleaseZip('1.2.0');
        $uploaded = new UploadedFile($zipPath, 'core-release.zip', 'application/zip', null, true);

        $this->actingAs($superAdmin)
            ->post('/admin/updates/upload', [
                'release_zip' => $uploaded,
            ])
            ->assertRedirect('/admin/updates')
            ->assertSessionHas('status');

        $state = ThemeSetting::query()->where('key', 'core_update_state')->first();
        $this->assertNotNull($state);
        $pending = $state->settings['pending_package'] ?? null;
        $this->assertIsArray($pending);
        $this->assertSame('manual', $pending['source'] ?? null);
        $this->assertSame('1.2.0', $pending['version'] ?? null);
        $this->assertFileExists((string) ($pending['zip_path'] ?? ''));

        $this->assertDatabaseHas('cms_core_update_logs', [
            'action' => 'upload',
            'status' => 'success',
            'to_version' => '1.2.0',
        ]);
    }

    public function test_apply_update_in_deploy_hook_mode(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin3@testocms.local', 'superadmin');

        config()->set('updates.mode', 'deploy-hook');
        config()->set('updates.deploy_hook_url', 'https://ci.example.com/hooks/testocms');
        config()->set('updates.deploy_hook_token', 'abc123');

        ThemeSetting::query()->updateOrCreate(
            ['key' => 'core_update_state'],
            [
                'settings' => [
                    'available_release' => [
                        'version' => '1.3.0',
                        'channel' => 'stable',
                        'requires' => ['php' => '>=8.1', 'cms_from' => '1.0.0'],
                        'sha256' => str_repeat('b', 64),
                    ],
                ],
            ]
        );

        Http::fake([
            'https://ci.example.com/hooks/testocms' => Http::response(['ok' => true], 202),
        ]);

        $this->actingAs($superAdmin)
            ->post('/admin/updates/apply')
            ->assertRedirect('/admin/updates')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('cms_core_update_logs', [
            'action' => 'apply',
            'status' => 'success',
            'to_version' => '1.3.0',
        ]);
    }

    public function test_manual_rollback_restores_files_from_backup_snapshot(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin4@testocms.local', 'superadmin');

        $root = storage_path('framework/testing/core-updates-'.uniqid('', true));
        @mkdir($root, 0777, true);
        @mkdir($root.'/app', 0777, true);
        @mkdir($root.'/storage/framework', 0777, true);

        file_put_contents($root.'/app/example.txt', 'NEW');

        $backupDir = storage_path('framework/testing/core-updates-backup-'.uniqid('', true));
        @mkdir($backupDir.'/code/app', 0777, true);
        file_put_contents($backupDir.'/code/app/example.txt', 'OLD');
        file_put_contents($backupDir.'/manifest.json', json_encode([
            'paths' => [
                ['path' => 'app', 'exists' => true, 'type' => 'dir'],
            ],
        ], JSON_PRETTY_PRINT));

        $backup = CoreBackup::query()->create([
            'backup_key' => 'bkp_test_rollback',
            'from_version' => '1.0.0',
            'to_version' => '1.1.0',
            'status' => 'created',
            'backup_path' => $backupDir,
            'db_dump_path' => null,
            'manifest_path' => $backupDir.'/manifest.json',
            'restore_status' => null,
            'actor_id' => $superAdmin->id,
        ]);

        config()->set('updates.base_path', $root);

        $this->actingAs($superAdmin)
            ->post('/admin/updates/rollback/'.$backup->id)
            ->assertRedirect('/admin/updates')
            ->assertSessionHas('status');

        $this->assertSame('OLD', file_get_contents($root.'/app/example.txt'));

        $backup->refresh();
        $this->assertSame('rolled_back', $backup->status);
    }

    private function makeReleaseZip(string $version): string
    {
        $tmpDir = storage_path('framework/testing/core-updates-zip-'.uniqid('', true));
        @mkdir($tmpDir, 0777, true);
        file_put_contents($tmpDir.'/release.json', json_encode([
            'version' => $version,
            'build' => 'test',
            'signed_at' => now()->toIso8601String(),
            'compat' => [
                'php' => '>=8.1',
                'cms_from' => '1.0.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zipPath = storage_path('framework/testing/core-release-'.uniqid('', true).'.zip');
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Failed to create ZIP');
        }
        $zip->addFile($tmpDir.'/release.json', 'release.json');
        $zip->close();

        return $zipPath;
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
