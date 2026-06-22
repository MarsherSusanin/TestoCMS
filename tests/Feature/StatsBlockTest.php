<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Content\Services\BlockLeafRendererService;
use App\Modules\Content\Services\PageContentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StatsBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_block_renders_value_and_label_cards(): void
    {
        $html = app(BlockLeafRendererService::class)->render('stats', [
            'items' => [
                ['value' => '500+', 'label' => 'Clients'],
                ['value' => '99%', 'label' => 'Uptime'],
                ['value' => '', 'label' => ''], // empty item is skipped
            ],
        ]);

        $this->assertStringContainsString('cms-stats', $html);
        $this->assertStringContainsString('cms-stat-value">500+', $html);
        $this->assertStringContainsString('cms-stat-label">Clients', $html);
        $this->assertStringContainsString('99%', $html);
        // Exactly two cards (the empty one dropped).
        $this->assertSame(2, substr_count($html, 'class="cms-stat"'));
    }

    public function test_stats_block_persists_through_save_and_is_a_meaningful_block(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->create([
            'name' => 'Admin', 'login' => 'stats_admin',
            'email' => 'stats_admin@testocms.local', 'password' => Hash::make('password'),
        ]);
        $admin->assignRole('superadmin');

        $page = app(PageContentService::class)->createFromValidated([
            'status' => 'published',
            'page_type' => 'landing',
            'translations' => [[
                'locale' => 'en',
                'title' => 'Stats Page',
                'slug' => 'stats',
                'content_blocks' => [
                    ['type' => 'stats', 'data' => ['items' => [['value' => '10x', 'label' => 'Faster']]]],
                ],
            ]],
        ], $admin, ['require_default_locale' => false]);

        $translation = $page->translations()->where('locale', 'en')->firstOrFail();
        // The stats block survived normalization (was kept as a meaningful block).
        $this->assertStringContainsString('stats', json_encode($translation->content_blocks));
        $this->assertStringContainsString('cms-stats', (string) $translation->rendered_html);
        $this->assertStringContainsString('10x', (string) $translation->rendered_html);
    }
}
