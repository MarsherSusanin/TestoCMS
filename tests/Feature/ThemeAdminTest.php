<?php

namespace Tests\Feature;

use App\Models\ThemeSetting;
use App\Models\User;
use App\Modules\Core\Services\SiteChromeNormalizerService;
use App\Modules\Core\Services\ThemeSettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ThemeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_open_theme_editor_with_boot_payload_and_runtimes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin-theme@testocms.local', 'superadmin');

        $this->actingAs($superAdmin)
            ->get('/admin/theme')
            ->assertOk()
            ->assertSee('testocms-theme-editor-boot', false)
            ->assertSee('/admin/runtime/editor-shared.js', false)
            ->assertSee('/admin/runtime/theme-builder.js', false)
            ->assertSee('/admin/runtime/chrome-builder.js', false)
            ->assertSee('chrome-payload-input', false);
    }

    public function test_theme_update_persists_theme_setting(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin-theme-save@testocms.local', 'superadmin');

        /** @var ThemeSettingsService $themeSettings */
        $themeSettings = app(ThemeSettingsService::class);
        $payload = $themeSettings->defaultTheme();
        $payload['preset_key'] = 'cobalt_glass';
        $payload['body_font'] = 'inter';
        $payload['heading_font'] = 'sora';
        $payload['mono_font'] = 'jetbrains_mono';
        $payload['colors']['accent'] = '#123456';
        $payload['colors']['brand'] = '#654321';

        $this->actingAs($superAdmin)
            ->put('/admin/theme', $payload)
            ->assertRedirect('/admin/theme')
            ->assertSessionHas('status', 'Theme saved.');

        $record = ThemeSetting::query()->where('key', 'default')->first();
        $this->assertNotNull($record);
        $this->assertSame('cobalt_glass', $record->settings['preset_key'] ?? null);
        $this->assertSame('#123456', $record->settings['colors']['accent'] ?? null);
        $this->assertSame('#654321', $record->settings['colors']['brand'] ?? null);
    }

    public function test_theme_chrome_update_persists_site_chrome_setting(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superAdmin = $this->makeUser('superadmin-theme-chrome@testocms.local', 'superadmin');

        $payload = [
            'header' => [
                'enabled' => true,
                'variant' => 'split_nav',
                'logo' => [
                    'src' => 'https://cdn.test/logo.svg',
                    'alt' => 'TestoCMS logo',
                ],
                'menu_position' => 'center',
                'show_brand_subtitle' => true,
                'show_locale_switcher' => true,
                'show_search' => true,
                'search_placement' => 'header',
                'nav_items' => [
                    [
                        'id' => 'services',
                        'enabled' => true,
                        'url' => '',
                        'new_tab' => false,
                        'nofollow' => false,
                        'label_translations' => ['ru' => 'Услуги', 'en' => 'Services'],
                        'children' => [[
                            'id' => 'consulting',
                            'enabled' => true,
                            'url' => '/{locale}/consulting',
                            'new_tab' => false,
                            'nofollow' => false,
                            'label_translations' => ['ru' => 'Консалтинг', 'en' => 'Consulting'],
                        ]],
                    ],
                    [
                        'id' => 'home',
                        'enabled' => true,
                        'url' => '/{locale}',
                        'link_target' => ['type' => 'page', 'id' => 15],
                        'new_tab' => false,
                        'nofollow' => false,
                        'label_translations' => ['ru' => 'Главная', 'en' => 'Home'],
                    ],
                ],
                'cta_buttons' => [],
            ],
            'footer' => [
                'enabled' => true,
                'variant' => 'inline',
                'show_brand' => true,
                'show_tagline' => true,
                'tagline_translations' => ['ru' => 'Подвал', 'en' => 'Footer'],
                'links' => [],
                'social_links' => [],
                'legal_links' => [],
            ],
            'search' => [
                'enabled' => true,
                'path_slug' => 'search',
                'scope_default' => 'all',
                'results_per_page' => 12,
                'min_query_length' => 2,
                'placeholder_translations' => ['ru' => 'Поиск', 'en' => 'Search'],
            ],
        ];

        $this->actingAs($superAdmin)
            ->put('/admin/theme/chrome', [
                'chrome_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])
            ->assertRedirect('/admin/theme')
            ->assertSessionHas('status', 'Header/Footer/Search settings saved.');

        $record = ThemeSetting::query()->where('key', 'site_chrome')->first();
        $this->assertNotNull($record);
        $navItems = $record->settings['header']['nav_items'] ?? [];
        $this->assertSame('https://cdn.test/logo.svg', $record->settings['header']['logo']['src'] ?? null);
        $this->assertSame('TestoCMS logo', $record->settings['header']['logo']['alt'] ?? null);
        $this->assertSame('center', $record->settings['header']['menu_position'] ?? null);
        $this->assertCount(2, $navItems);
        $this->assertCount(1, $navItems[0]['children'] ?? []);
        $this->assertSame('/{locale}/consulting', $navItems[0]['children'][0]['url'] ?? null);
        $this->assertSame([], $navItems[1]['children'] ?? null);
        $this->assertSame('page', $navItems[1]['link_target']['type'] ?? null);
        $this->assertSame(15, $navItems[1]['link_target']['id'] ?? null);
    }

    public function test_chrome_logo_src_drops_dangerous_schemes(): void
    {
        $service = app(SiteChromeNormalizerService::class);

        $bad = $service->normalizeForSave(['header' => ['logo' => ['src' => 'javascript:alert(1)', 'alt' => 'x']]]);
        $this->assertSame('', data_get($bad, 'header.logo.src'));

        $https = $service->normalizeForSave(['header' => ['logo' => ['src' => 'https://cdn.test/logo.svg']]]);
        $this->assertSame('https://cdn.test/logo.svg', data_get($https, 'header.logo.src'));

        $relative = $service->normalizeForSave(['header' => ['logo' => ['src' => '/storage/logo.png']]]);
        $this->assertSame('/storage/logo.png', data_get($relative, 'header.logo.src'));
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
