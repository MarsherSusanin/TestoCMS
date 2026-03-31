<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPublicLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_uses_public_layout_with_header_footer_and_search(): void
    {
        ThemeSetting::query()->updateOrCreate(
            ['key' => 'default'],
            ['settings' => [
                'preset_key' => 'warm_editorial',
                'body_font' => 'manrope',
                'heading_font' => 'space_grotesk',
                'mono_font' => 'ibm_plex_mono',
                'colors' => [
                    'bg' => '#F4F1EA',
                    'bg_start' => '#F7F3EB',
                    'bg_end' => '#F1EDE4',
                    'surface' => '#FFFFFF',
                    'surface_tint' => '#FFFFFF',
                    'text' => '#111827',
                    'muted' => '#5B6475',
                    'line' => '#E0D8CC',
                    'line_strong' => '#CFC5B6',
                    'brand' => '#654321',
                    'brand_deep' => '#B5361D',
                    'brand_alt' => '#EF7F1A',
                    'accent' => '#123456',
                    'accent_2' => '#1D3557',
                    'success' => '#0F766E',
                ],
            ]]
        );

        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Home',
            'slug' => 'home',
            'content_blocks' => [],
            'rendered_html' => '<p>Home</p>',
        ]);

        $this->get('/en')
            ->assertOk()
            ->assertSee('TestoCMS', false)
            ->assertSee('<meta name="theme-color" content="#123456">', false)
            ->assertSee('--brand: #654321;', false)
            ->assertSee('site-search-form', false)
            ->assertSee('site-footer', false);
    }

    public function test_post_uses_public_layout_shell(): void
    {
        $post = Post::query()->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Hello',
            'slug' => 'hello',
            'content_html' => '<p>Hello world</p>',
            'content_plain' => 'Hello world',
        ]);

        $this->get('/en/blog/hello')
            ->assertOk()
            ->assertSee('Hello', false)
            ->assertSee('topbar', false)
            ->assertSee('site-footer', false);
    }

    public function test_search_route_renders_results_with_public_shell(): void
    {
        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now(),
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Searchable Home',
            'slug' => 'home',
            'content_blocks' => [],
            'rendered_html' => '<p>Findable phrase</p>',
            'meta_description' => 'Findable phrase',
        ]);

        $this->get('/en/search?q=Findable')
            ->assertOk()
            ->assertSee('Searchable Home')
            ->assertSee('site-search-form', false);
    }

    public function test_category_route_renders_published_posts(): void
    {
        $category = Category::query()->create([
            'is_active' => true,
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'locale' => 'en',
            'title' => 'News',
            'slug' => 'news',
            'description' => 'Latest updates',
        ]);

        $post = Post::query()->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Category post',
            'slug' => 'category-post',
            'content_html' => '<p>Category item</p>',
            'content_plain' => 'Category item',
        ]);

        $post->categories()->attach($category->id);

        $this->get('/en/category/news')
            ->assertOk()
            ->assertSee('News')
            ->assertSee('Category post');
    }
}
