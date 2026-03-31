<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalBaselineBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_requests_restore_demo_baseline_when_content_is_empty(): void
    {
        config([
            'cms.default_locale' => 'ru',
            'cms.seed_demo_content' => true,
        ]);

        $this->assertSame($this->emptyCounts(), $this->contentCounts());

        $this->get('/')
            ->assertRedirect('/ru');

        $this->get('/ru')
            ->assertOk()
            ->assertSee('TestoCMS Демо')
            ->assertSee('Открыть админку');

        $this->get('/ru/blog')
            ->assertOk()
            ->assertSee('Добро пожаловать в TestoCMS');

        $this->assertSame($this->demoCounts(), $this->contentCounts());
        $this->assertNotNull(User::query()->where('email', 'admin@testocms.local')->first());
    }

    public function test_admin_login_bootstraps_default_admin_and_demo_content_from_empty_database(): void
    {
        config([
            'cms.default_locale' => 'ru',
            'cms.seed_demo_content' => true,
        ]);

        $this->get('/admin/login')
            ->assertOk();

        $this->assertSame($this->demoCounts(), $this->contentCounts());

        $this->post('/admin/login', [
            'email' => 'admin@testocms.local',
            'password' => 'ChangeMe123!',
        ])->assertRedirect('/admin');
    }

    public function test_recovery_does_not_duplicate_demo_content_when_baseline_already_exists(): void
    {
        config([
            'cms.default_locale' => 'ru',
            'cms.seed_demo_content' => true,
        ]);

        $this->get('/ru')->assertOk();
        $this->assertSame($this->demoCounts(), $this->contentCounts());

        $this->get('/ru')->assertOk();
        $this->get('/admin/login')->assertOk();

        $this->assertSame($this->demoCounts(), $this->contentCounts());
    }

    public function test_empty_database_does_not_self_seed_when_demo_content_is_disabled(): void
    {
        config([
            'cms.default_locale' => 'ru',
            'cms.seed_demo_content' => false,
        ]);

        $this->get('/ru')->assertNotFound();

        $this->assertSame($this->emptyCounts(), $this->contentCounts());
        $this->assertNull(User::query()->where('email', 'admin@testocms.local')->first());
    }

    /**
     * @return array<string, int>
     */
    private function contentCounts(): array
    {
        return [
            'pages' => Page::query()->count(),
            'page_translations' => PageTranslation::query()->count(),
            'posts' => Post::query()->count(),
            'post_translations' => PostTranslation::query()->count(),
            'categories' => Category::query()->count(),
            'category_translations' => CategoryTranslation::query()->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'pages' => 0,
            'page_translations' => 0,
            'posts' => 0,
            'post_translations' => 0,
            'categories' => 0,
            'category_translations' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function demoCounts(): array
    {
        return [
            'pages' => 2,
            'page_translations' => 4,
            'posts' => 1,
            'post_translations' => 2,
            'categories' => 1,
            'category_translations' => 2,
        ];
    }
}
