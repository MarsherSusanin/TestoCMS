<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $author = User::query()->create([
            'name' => 'Author',
            'login' => 'search_author',
            'email' => 'search_author@testocms.local',
            'password' => Hash::make('password'),
        ]);

        $post = Post::query()->create([
            'author_id' => $author->id,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Unique Widget Guide',
            'slug' => 'widget-guide',
            'content_format' => 'html',
            'content_html' => '<p>body</p>',
            'content_plain' => 'body',
            'excerpt' => 'A short guide.',
        ]);

        $page = Page::query()->create([
            'status' => 'published',
            'page_type' => 'landing',
            'published_at' => now()->subDay(),
        ]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Landing Home',
            'slug' => 'home',
            'content_blocks' => [],
            'rendered_html' => '<p>body</p>',
            'meta_description' => 'distincttoken in meta',
        ]);
    }

    public function test_search_matches_post_title(): void
    {
        $this->get('/en/search?q=Widget')->assertOk()->assertSee('Unique Widget Guide');
    }

    public function test_search_matches_page_meta_description(): void
    {
        $this->get('/en/search?q=distincttoken')->assertOk()->assertSee('Landing Home');
    }

    public function test_search_returns_nothing_for_unmatched_query(): void
    {
        $this->get('/en/search?q=zzznomatchzzz')
            ->assertOk()
            ->assertDontSee('Unique Widget Guide')
            ->assertDontSee('Landing Home');
    }
}
