<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\ContentRevision;
use App\Models\ContentTemplate;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Modules\Content\Services\AssetDeletionJournal;
use App\Modules\Content\Services\AssetUsageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class AssetDeletionSafetyTest extends TestCase
{
    // Deletion coordinates its own commit and filesystem rollback, so these
    // tests intentionally do not wrap the HTTP request in a test transaction.
    use DatabaseMigrations;

    private string $journalRoot;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms.seed_demo_content' => false, 'app.url' => 'http://localhost']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $actor = User::create(['name' => 'Media admin', 'login' => 'media_admin', 'email' => 'media@audit.local', 'password' => 'password', 'status' => 'active']);
        $actor->assignRole('superadmin');
        $this->withToken($actor->createToken('media', ['assets:write'])->plainTextToken);
        Storage::fake('public');
        $this->journalRoot = storage_path('app/private/testocms-p2-media-').bin2hex(random_bytes(8));
        $journal = new class($this->journalRoot) extends AssetDeletionJournal
        {
            public function __construct(private readonly string $directory) {}

            public function root(): string
            {
                return $this->directory;
            }
        };
        $this->app->instance(AssetDeletionJournal::class, $journal);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->journalRoot);
        parent::tearDown();
    }

    private function asset(string $path = 'assets/a.jpg'): Asset
    {
        Storage::disk('public')->put($path, 'original-bytes');

        return Asset::create(['disk' => 'public', 'storage_path' => $path, 'public_url' => '/storage/'.$path, 'type' => 'image', 'mime_type' => 'image/jpeg', 'size' => 14]);
    }

    public function test_api_removes_file_and_record_and_retains_private_tombstone(): void
    {
        $asset = $this->asset();
        $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertNoContent();
        Storage::disk('public')->assertMissing($asset->storage_path);
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        $entries = app(AssetDeletionJournal::class)->entries();
        $this->assertSame('deleted', $entries[0]['state']);
        $this->assertNull($entries[0]['quarantine']);
        $this->assertSame(0600, fileperms(app(AssetDeletionJournal::class)->path($entries[0]['id'])) & 0777);
        $this->expectException(ValidationException::class);
        app(AssetUsageService::class)->assertReferencesAvailable([['src' => '/storage/assets/a.jpg']]);
    }

    public function test_shared_storage_path_is_preserved_until_last_owner(): void
    {
        $asset = $this->asset();
        $other = $asset->replicate();
        $other->save();
        $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertNoContent();
        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertDatabaseHas('assets', ['id' => $other->id]);
        $this->deleteJson('/api/admin/v1/assets/'.$other->id)->assertNoContent();
        Storage::disk('public')->assertMissing($asset->storage_path);
    }

    public function test_foreign_keys_block_deletion_with_actionable_usages(): void
    {
        $asset = $this->asset();
        $post = Post::create(['featured_asset_id' => $asset->id, 'status' => 'draft']);
        $category = Category::create(['cover_asset_id' => $asset->id]);
        $response = $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertConflict()->assertJsonPath('error', 'asset_in_use');
        $this->assertCount(2, $response->json('usages'));
        $this->assertSame($post->id, $response->json('usages.0.id'));
        $this->assertSame($category->id, $response->json('usages.1.id'));
        Storage::disk('public')->assertExists($asset->storage_path);
    }

    public function test_nested_urls_html_markdown_custom_code_theme_and_templates_are_live_usages(): void
    {
        $asset = $this->asset();
        $page = Page::create(['status' => 'draft', 'custom_code' => ['js' => 'const image="/storage/assets/a.jpg";']]);
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'en', 'title' => 'Media', 'slug' => 'media', 'content_blocks' => [['type' => 'container', 'children' => [['type' => 'image', 'data' => ['src' => 'http://localhost/storage/assets/a.jpg?cache=2']]]]], 'rendered_html' => '<img src="/storage/assets/a.jpg">']);
        $post = Post::create(['status' => 'draft']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Markdown', 'slug' => 'markdown', 'content_markdown' => '![Image](/storage/assets/a.jpg)', 'content_format' => 'markdown']);
        ContentTemplate::create(['entity_type' => 'page', 'name' => 'Media template', 'payload' => ['blocks' => [['src' => '/storage/assets/a.jpg']]], 'is_active' => true]);
        ThemeSetting::create(['key' => 'site_chrome', 'settings' => ['header' => ['logo' => ['src' => '/storage/assets/a.jpg']]]]);
        $response = $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertConflict();
        $types = array_column($response->json('usages'), 'entity_type');
        foreach (['page', 'post', 'template', 'theme'] as $type) {
            $this->assertContains($type, $types);
        }
    }

    public function test_db_failure_restores_local_bytes_and_retains_record(): void
    {
        $asset = $this->asset();
        Asset::deleting(static function (): void {
            throw new \RuntimeException('Simulated database rollback');
        });
        try {
            $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertStatus(503)->assertJsonPath('error', 'asset_storage_cleanup_failed');
            $this->assertDatabaseHas('assets', ['id' => $asset->id]);
            $this->assertSame('original-bytes', Storage::disk('public')->get($asset->storage_path));
            $this->assertSame([], app(AssetDeletionJournal::class)->entries());
        } finally {
            Asset::flushEventListeners();
        }
    }

    public function test_quarantine_journal_recovers_interrupted_local_rename(): void
    {
        $asset = $this->asset();
        $journal = app(AssetDeletionJournal::class);
        $id = str_repeat('a', 32);
        $entry = ['id' => $id, 'asset_id' => $asset->id, 'disk' => 'public', 'path' => $asset->storage_path, 'local' => true, 'state' => 'prepared', 'urls' => [$asset->public_url], 'quarantine' => $journal->root().'/'.$id.'.bin', 'had_source' => true, 'mode' => 0644];
        $journal->write($entry);
        rename(Storage::disk('public')->path($asset->storage_path), $entry['quarantine']);
        $this->artisan('cms:assets:recover')->expectsOutputToContain('Restored: 1; purged: 0; failed: 0')->assertSuccessful();
        $this->assertSame('original-bytes', Storage::disk('public')->get($asset->storage_path));
    }

    public function test_remote_cleanup_false_retains_record_and_retry_can_succeed(): void
    {
        config(['filesystems.disks.remote_fixture' => ['driver' => 's3']]);
        $asset = Asset::create(['disk' => 'remote_fixture', 'storage_path' => 'assets/remote.jpg', 'public_url' => 'https://media.example/remote.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg', 'size' => 3]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('url')->andReturn('https://media.example/remote.jpg');
        $disk->shouldReceive('exists')->andReturnUsing(static fn ($path) => $path === 'assets/remote.jpg');
        $disk->shouldReceive('getVisibility')->andReturn('public');
        $disk->shouldReceive('copy')->andReturn(true);
        $disk->shouldReceive('setVisibility')->andReturn(true);
        $disk->shouldReceive('delete')->andReturn(false);
        Storage::set('remote_fixture', $disk);
        $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertStatus(503);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_local_symlink_escape_does_not_touch_external_bytes(): void
    {
        $asset = $this->asset();
        $outside = $this->journalRoot.'-outside';
        file_put_contents($outside, 'external');
        unlink(Storage::disk('public')->path($asset->storage_path));
        symlink($outside, Storage::disk('public')->path($asset->storage_path));
        try {
            $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertStatus(503);
            $this->assertSame('external', file_get_contents($outside));
            $this->assertDatabaseHas('assets', ['id' => $asset->id]);
        } finally {
            unlink($outside);
        }
    }

    public function test_external_urls_similar_filenames_and_historical_revisions_do_not_block(): void
    {
        $asset = $this->asset();
        $page = Page::create(['status' => 'draft']);
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'en', 'title' => 'Unrelated', 'slug' => 'unrelated', 'rendered_html' => '<img src="https://other.example/storage/assets/a.jpg"><img src="/storage/assets/a.jpg.bak">']);
        ContentRevision::create(['entity_type' => 'page', 'entity_id' => $page->id, 'payload' => ['src' => $asset->public_url]]);
        $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertNoContent();
    }

    public function test_recovery_purges_private_bytes_after_a_committed_interrupted_delete(): void
    {
        $asset = $this->asset();
        $journal = app(AssetDeletionJournal::class);
        $id = str_repeat('b', 32);
        $entry = ['id' => $id, 'asset_id' => $asset->id, 'disk' => 'public', 'path' => $asset->storage_path, 'local' => true, 'state' => 'prepared', 'urls' => [$asset->public_url], 'quarantine' => $journal->root().'/'.$id.'.bin', 'had_source' => true];
        $journal->write($entry);
        rename(Storage::disk('public')->path($asset->storage_path), $entry['quarantine']);
        $asset->delete();
        $this->artisan('cms:assets:recover')->expectsOutputToContain('Restored: 0; purged: 1; failed: 0')->assertSuccessful();
        $this->assertFileDoesNotExist($entry['quarantine']);
        $this->assertSame('deleted', $journal->entries()[0]['state']);
        $this->artisan('cms:assets:recover')->expectsOutputToContain('Restored: 0; purged: 0; failed: 0')->assertSuccessful();
    }

    public function test_remote_success_purges_backup_and_records_tombstone(): void
    {
        config(['filesystems.disks.remote_fixture' => ['driver' => 's3']]);
        $asset = Asset::create(['disk' => 'remote_fixture', 'storage_path' => 'assets/remote.jpg', 'public_url' => 'https://media.example/remote.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg', 'size' => 3]);
        $objects = ['assets/remote.jpg' => 'remote-bytes'];
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('url')->andReturn('https://media.example/remote.jpg');
        $disk->shouldReceive('exists')->andReturnUsing(static function ($path) use (&$objects): bool {
            return isset($objects[$path]);
        });
        $disk->shouldReceive('getVisibility')->andReturn('public');
        $disk->shouldReceive('copy')->andReturnUsing(static function ($from, $to) use (&$objects): bool {
            $objects[$to] = $objects[$from];

            return true;
        });
        $disk->shouldReceive('setVisibility')->andReturn(true);
        $disk->shouldReceive('delete')->andReturnUsing(static function ($path) use (&$objects): bool {
            unset($objects[$path]);

            return true;
        });
        Storage::set('remote_fixture', $disk);
        $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertNoContent();
        $this->assertSame([], $objects);
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        $this->assertSame('deleted', app(AssetDeletionJournal::class)->entries()[0]['state']);
    }

    public function test_post_commit_private_purge_failure_is_reported_and_recoverable(): void
    {
        config(['filesystems.disks.remote_fixture' => ['driver' => 's3']]);
        $asset = Asset::create(['disk' => 'remote_fixture', 'storage_path' => 'assets/purge.jpg', 'public_url' => 'https://media.example/purge.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg', 'size' => 3]);
        $objects = ['assets/purge.jpg' => 'original-bytes'];
        $failPurge = true;
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('url')->andReturn('https://media.example/purge.jpg');
        $disk->shouldReceive('exists')->andReturnUsing(static function ($path) use (&$objects): bool {
            return isset($objects[$path]);
        });
        $disk->shouldReceive('getVisibility')->andReturn('public');
        $disk->shouldReceive('copy')->andReturnUsing(static function ($from, $to) use (&$objects): bool {
            $objects[$to] = $objects[$from];

            return true;
        });
        $disk->shouldReceive('setVisibility')->andReturn(true);
        $disk->shouldReceive('delete')->andReturnUsing(static function ($path) use (&$objects, &$failPurge): bool {
            if ($failPurge && str_starts_with($path, '.cms-private/')) {
                return false;
            }
            unset($objects[$path]);

            return true;
        });
        Storage::set('remote_fixture', $disk);
        $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertStatus(503)->assertJsonPath('error', 'asset_storage_cleanup_failed');
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        $this->assertArrayNotHasKey('assets/purge.jpg', $objects);
        $entry = app(AssetDeletionJournal::class)->entries()[0];
        $this->assertSame('prepared', $entry['state']);
        $this->assertSame('original-bytes', $objects[$entry['quarantine']]);
        $failPurge = false;
        $this->artisan('cms:assets:recover')->expectsOutputToContain('Restored: 0; purged: 1; failed: 0')->assertSuccessful();
        $this->assertSame([], $objects);
        $this->assertSame('deleted', app(AssetDeletionJournal::class)->entries()[0]['state']);
    }

    public function test_missing_file_is_idempotent_and_reuploaded_tombstone_path_is_allowed(): void
    {
        $asset = $this->asset();
        Storage::disk('public')->delete($asset->storage_path);
        $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertNoContent();
        Storage::disk('public')->put($asset->storage_path, 'new-bytes');
        app(AssetUsageService::class)->assertReferencesAvailable(['src' => $asset->public_url]);
        $this->assertSame('new-bytes', Storage::disk('public')->get($asset->storage_path));
    }

    public function test_private_local_files_are_not_deleted_as_registered_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('setup/internal.key', 'internal-bytes');
        $asset = Asset::create(['disk' => 'local', 'storage_path' => 'setup/internal.key', 'type' => 'document', 'mime_type' => 'text/plain', 'size' => 14]);
        $this->deleteJson('/api/admin/v1/assets/'.$asset->id)->assertStatus(503);
        $this->assertSame('internal-bytes', Storage::disk('local')->get('setup/internal.key'));
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }
}
