<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Modules\Content\Services\BlockLeafRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * post_listing previously rendered a permanently-empty div (no hydration), so
 * the block was invisible to users and crawlers. It must now server-render the
 * published posts.
 */
class PostListingBlockTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::query()->create([
            'name' => 'Author',
            'login' => 'pl_author',
            'email' => 'pl_author@testocms.local',
            'password' => Hash::make('password'),
        ]);
    }

    private function makePost(User $author, string $status, string $title, string $slug, ?\DateTimeInterface $publishedAt): void
    {
        $post = Post::query()->create([
            'author_id' => $author->id,
            'status' => $status,
            'published_at' => $publishedAt,
        ]);

        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => $title,
            'slug' => $slug,
            'content_format' => 'html',
            'content_html' => '<p>body</p>',
            'content_plain' => 'body',
            'excerpt' => $title.' excerpt',
        ]);
    }

    public function test_post_listing_renders_published_posts_and_hides_drafts_and_embargoed(): void
    {
        $author = $this->author();
        $this->makePost($author, 'published', 'Hello World', 'hello-world', now()->subDay());
        $this->makePost($author, 'draft', 'Draft Post', 'draft-post', null);
        $this->makePost($author, 'published', 'Future Post', 'future-post', now()->addDay());

        $html = app(BlockLeafRendererService::class)->render('post_listing', ['limit' => 10], ['locale' => 'en']);

        $this->assertStringContainsString('cms-post-listing', $html);
        $this->assertStringContainsString('Hello World', $html);
        $this->assertStringContainsString(url('/en/blog/hello-world'), $html);
        $this->assertStringContainsString('Hello World excerpt', $html);

        $this->assertStringNotContainsString('Draft Post', $html);
        $this->assertStringNotContainsString('Future Post', $html);
    }
}
