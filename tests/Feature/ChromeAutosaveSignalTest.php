<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mirrors AutosaveSignalTest for the theme/chrome builder: a successful
 * chrome save must signal justSaved so the client drops its stale local
 * draft instead of offering to restore it over the saved settings.
 */
class ChromeAutosaveSignalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::query()->create([
            'name' => 'Admin', 'login' => 'chrome_autosave_admin', 'status' => 'active',
            'email' => 'chrome_autosave_admin@testocms.local', 'password' => Hash::make('password'),
        ]);
        $user->assignRole('superadmin');

        return $user;
    }

    public function test_chrome_save_signals_just_saved_only_after_a_save(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put('/admin/theme/chrome', [
                'chrome_payload' => json_encode(['header' => ['enabled' => true]]),
            ])
            ->assertRedirect('/admin/theme')
            ->assertSessionHas('chrome_saved', true);

        $saved = $this->actingAs($admin)
            ->withSession(['chrome_saved' => true])
            ->get('/admin/theme')->assertOk()->getContent();
        $this->assertStringContainsString('"justSaved":true', $saved);

        $plain = $this->actingAs($admin)->get('/admin/theme')->assertOk()->getContent();
        $this->assertStringContainsString('"justSaved":false', $plain);
    }
}
