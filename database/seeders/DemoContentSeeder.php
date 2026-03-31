<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Core\Contracts\BlockRendererContract;
use App\Modules\Core\Contracts\SanitizerContract;
use Illuminate\Database\Seeder;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! (bool) config('cms.seed_demo_content', true)) {
            return;
        }

        $authorId = User::query()->orderBy('id')->value('id');
        $sanitizer = app(SanitizerContract::class);
        $blockRenderer = app(BlockRendererContract::class);

        [$category] = $this->ensureDemoCategory();
        $this->ensureDemoPost($authorId, $category->id, $sanitizer);
        $this->ensureHomePage($authorId, $blockRenderer);
        $this->ensureAboutPage($authorId, $blockRenderer);

        app(PageCacheService::class)->flushAll();
    }

    /**
     * @return array{0: Category, 1: array<string, CategoryTranslation>}
     */
    private function ensureDemoCategory(): array
    {
        $enTranslation = CategoryTranslation::query()
            ->where('locale', 'en')
            ->where('slug', 'news')
            ->first();

        $category = $enTranslation?->category;
        if ($category === null) {
            $category = Category::query()->create([
                'is_active' => true,
            ]);
        }

        $translations = [];
        foreach ([
            'en' => [
                'title' => 'News',
                'slug' => 'news',
                'description' => 'Product updates, releases and editorial announcements.',
                'meta_title' => 'News',
                'meta_description' => 'News and updates from the TestoCMS demo site.',
            ],
            'ru' => [
                'title' => 'Новости',
                'slug' => 'novosti',
                'description' => 'Обновления продукта, релизы и редакционные анонсы.',
                'meta_title' => 'Новости',
                'meta_description' => 'Новости и обновления демо-сайта на TestoCMS.',
            ],
        ] as $locale => $data) {
            $translation = CategoryTranslation::query()->firstOrCreate(
                [
                    'category_id' => $category->id,
                    'locale' => $locale,
                ],
                $data
            );
            $translations[$locale] = $translation;
        }

        return [$category, $translations];
    }

    private function ensureDemoPost(?int $authorId, int $categoryId, SanitizerContract $sanitizer): void
    {
        $enTranslation = PostTranslation::query()
            ->where('locale', 'en')
            ->where('slug', 'welcome-to-testocms')
            ->first();

        $post = $enTranslation?->post;
        if ($post === null) {
            $post = Post::query()->create([
                'author_id' => $authorId,
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        $post->categories()->syncWithoutDetaching([$categoryId]);

        $translations = [
            'en' => [
                'title' => 'Welcome to TestoCMS',
                'slug' => 'welcome-to-testocms',
                'content_html' => '<p>This demo post is created automatically for local Docker setup.</p><p>Use the admin panel to edit, schedule, preview and publish content.</p>',
                'excerpt' => 'Demo post created automatically for local Docker setup.',
                'meta_title' => 'Welcome to TestoCMS',
                'meta_description' => 'Demo article created automatically in local Docker deployment.',
            ],
            'ru' => [
                'title' => 'Добро пожаловать в TestoCMS',
                'slug' => 'dobro-pozhalovat-v-testocms',
                'content_html' => '<p>Этот демонстрационный пост создается автоматически для локального запуска в Docker.</p><p>Используйте админку для редактирования, предпросмотра и публикации контента.</p>',
                'excerpt' => 'Демо-пост для локального запуска в Docker.',
                'meta_title' => 'Добро пожаловать в TestoCMS',
                'meta_description' => 'Демонстрационная статья для локального развертывания.',
            ],
        ];

        foreach ($translations as $locale => $data) {
            $html = $sanitizer->sanitizeHtml($data['content_html']);
            PostTranslation::query()->firstOrCreate(
                [
                    'post_id' => $post->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'content_html' => $html,
                    'content_plain' => trim(strip_tags($html)),
                    'excerpt' => $data['excerpt'],
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['meta_description'],
                ]
            );
        }
    }

    private function ensureHomePage(?int $authorId, BlockRendererContract $blockRenderer): void
    {
        $enTranslation = PageTranslation::query()
            ->where('locale', 'en')
            ->where('slug', 'home')
            ->first();

        $page = $enTranslation?->page;
        if ($page === null) {
            $page = Page::query()->create([
                'author_id' => $authorId,
                'status' => 'published',
                'page_type' => 'landing',
                'published_at' => now(),
            ]);
        }

        $blocksByLocale = [
            'en' => [
                ['type' => 'heading', 'data' => ['level' => 1, 'text' => 'TestoCMS Demo Homepage']],
                ['type' => 'rich_text', 'data' => ['html' => '<p>The local Docker build is working. This page is server-rendered and indexed without JavaScript.</p><p>Create/edit pages, posts, categories and assets from the admin UI.</p>']],
                ['type' => 'cta', 'data' => ['label' => 'Open Admin', 'url' => '/admin']],
                ['type' => 'cta', 'data' => ['label' => 'Read Blog', 'url' => '/en/'.config('cms.post_url_prefix', 'blog')]],
            ],
            'ru' => [
                ['type' => 'heading', 'data' => ['level' => 1, 'text' => 'Главная страница TestoCMS (демо)']],
                ['type' => 'rich_text', 'data' => ['html' => '<p>Локальная Docker-сборка работает. Страница отрисовывается на сервере и индексируется без JavaScript.</p><p>Управляйте страницами, постами, рубриками и медиа из админки.</p>']],
                ['type' => 'cta', 'data' => ['label' => 'Открыть админку', 'url' => '/admin']],
                ['type' => 'cta', 'data' => ['label' => 'Читать блог', 'url' => '/ru/'.config('cms.post_url_prefix', 'blog')]],
            ],
        ];

        foreach ($blocksByLocale as $locale => $blocks) {
            PageTranslation::query()->firstOrCreate(
                [
                    'page_id' => $page->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $locale === 'en' ? 'Home' : 'Главная',
                    'slug' => 'home',
                    'content_blocks' => $blocks,
                    'rendered_html' => $blockRenderer->render($blocks),
                    'meta_title' => $locale === 'en' ? 'TestoCMS Demo' : 'TestoCMS Демо',
                    'meta_description' => $locale === 'en'
                        ? 'Demo homepage generated during local Docker setup.'
                        : 'Демо-главная страница, созданная при локальном запуске Docker.',
                ]
            );
        }
    }

    private function ensureAboutPage(?int $authorId, BlockRendererContract $blockRenderer): void
    {
        $enTranslation = PageTranslation::query()
            ->where('locale', 'en')
            ->where('slug', 'about')
            ->first();

        $page = $enTranslation?->page;
        if ($page === null) {
            $page = Page::query()->create([
                'author_id' => $authorId,
                'status' => 'published',
                'page_type' => 'standard',
                'published_at' => now(),
            ]);
        }

        $translations = [
            'en' => [
                'title' => 'About',
                'slug' => 'about',
                'html' => '<p>This page demonstrates a simple static page with rich text content blocks.</p>',
            ],
            'ru' => [
                'title' => 'О проекте',
                'slug' => 'o-proekte',
                'html' => '<p>Эта страница демонстрирует простую статическую страницу на блоках rich text.</p>',
            ],
        ];

        foreach ($translations as $locale => $data) {
            $blocks = [
                ['type' => 'heading', 'data' => ['level' => 1, 'text' => $data['title']]],
                ['type' => 'rich_text', 'data' => ['html' => $data['html']]],
            ];

            PageTranslation::query()->firstOrCreate(
                [
                    'page_id' => $page->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'content_blocks' => $blocks,
                    'rendered_html' => $blockRenderer->render($blocks),
                    'meta_title' => $data['title'],
                ]
            );
        }
    }
}
