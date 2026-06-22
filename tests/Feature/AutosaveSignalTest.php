<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * After a successful edit-save the editor boot must signal justSaved so the
 * client drops its now-stale autosave snapshot instead of offering to restore
 * it over the just-saved content (the edit form posts back to the same URL).
 */
class AutosaveSignalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::query()->create([
            'name' => 'Admin', 'login' => 'autosave_admin',
            'email' => 'autosave_admin@testocms.local', 'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole('superadmin');

        return $user;
    }

    public function test_edit_boot_signals_just_saved_only_after_a_save(): void
    {
        $admin = $this->admin();
        $page = Page::query()->create(['status' => 'draft', 'page_type' => 'landing']);
        PageTranslation::query()->create([
            'page_id' => $page->id, 'locale' => 'ru', 'title' => 'T', 'slug' => 'about',
            'content_blocks' => [], 'rendered_html' => '',
        ]);

        // A successful update flashes the content_saved signal.
        $this->actingAs($admin)
            ->put('/admin/pages/'.$page->id, [
                'status' => 'draft',
                'translations' => ['ru' => ['title' => 'Updated', 'slug' => 'about']],
            ])
            ->assertRedirect('/admin/pages/'.$page->id.'/edit')
            ->assertSessionHas('content_saved', true);

        // The edit boot surfaces that signal as justSaved, so the client drops
        // its stale snapshot instead of restoring over the saved content.
        $saved = $this->actingAs($admin)
            ->withSession(['content_saved' => true])
            ->get('/admin/pages/'.$page->id.'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('"justSaved":true', $saved);

        // A plain load (no save signal) → justSaved false.
        $plain = $this->actingAs($admin)->get('/admin/pages/'.$page->id.'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('"justSaved":false', $plain);
    }

    public function test_post_edit_boot_signals_just_saved_only_after_a_save(): void
    {
        $admin = $this->admin();
        $post = Post::query()->create(['author_id' => $admin->id, 'status' => 'draft']);
        PostTranslation::query()->create([
            'post_id' => $post->id, 'locale' => 'ru', 'title' => 'T', 'slug' => 'note',
            'content_format' => 'html', 'content_html' => '<p>x</p>', 'content_plain' => 'x',
        ]);

        $this->actingAs($admin)
            ->put('/admin/posts/'.$post->id, [
                'status' => 'draft',
                'translations' => ['ru' => [
                    'title' => 'Updated', 'slug' => 'note',
                    'content_format' => 'html', 'content_html' => '<p>y</p>',
                ]],
            ])
            ->assertRedirect('/admin/posts/'.$post->id.'/edit')
            ->assertSessionHas('content_saved', true);

        $saved = $this->actingAs($admin)
            ->withSession(['content_saved' => true])
            ->get('/admin/posts/'.$post->id.'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('"justSaved":true', $saved);

        $plain = $this->actingAs($admin)->get('/admin/posts/'.$post->id.'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('"justSaved":false', $plain);
    }
}
