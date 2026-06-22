<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Content\Services\PageContentService;
use App\Modules\I18n\Services\LocaleResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class I18nCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::query()->create([
            'name' => 'Admin',
            'login' => 'i18n_admin',
            'email' => 'i18n_admin@testocms.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('superadmin');

        return $user;
    }

    public function test_admin_api_rejects_unsupported_locale(): void
    {
        config()->set('cms.supported_locales', ['ru', 'en']);
        $token = $this->admin()->createToken('t', ['pages:write'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/v1/pages', [
                'status' => 'draft',
                'page_type' => 'landing',
                'translations' => [[
                    'locale' => 'zz',
                    'title' => 'Bad locale',
                    'slug' => 'bad-locale',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('translations.0.locale');
    }

    public function test_removed_locale_is_pruned_on_update(): void
    {
        config()->set('cms.supported_locales', ['ru', 'en']);
        $admin = $this->admin();
        $service = app(PageContentService::class);

        $page = $service->createFromValidated([
            'status' => 'draft',
            'page_type' => 'landing',
            'translations' => [
                ['locale' => 'ru', 'title' => 'Рус', 'slug' => 'pruned-ru'],
                ['locale' => 'en', 'title' => 'Eng', 'slug' => 'pruned-en'],
            ],
        ], $admin, ['require_default_locale' => false]);

        $this->assertSame(2, $page->translations()->count());

        // Update submitting only RU — the EN translation must be pruned.
        $service->updateFromValidated($page->fresh(), [
            'status' => 'draft',
            'page_type' => 'landing',
            'translations' => [
                ['locale' => 'ru', 'title' => 'Рус', 'slug' => 'pruned-ru'],
            ],
        ], $admin, ['require_default_locale' => false]);

        $this->assertDatabaseMissing('page_translations', ['slug' => 'pruned-en']);
        $this->assertDatabaseHas('page_translations', ['slug' => 'pruned-ru']);
    }

    public function test_effective_default_locale_falls_back_to_a_supported_one(): void
    {
        config()->set('cms.supported_locales', ['en']);
        config()->set('cms.default_locale', 'ru'); // not supported

        $this->assertSame('en', app(LocaleResolver::class)->effectiveDefault());

        // Site root must redirect to a routable locale, not /ru (404).
        $this->get('/')->assertRedirect('/en');
    }
}
