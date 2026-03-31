<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_api_requires_key_when_configured(): void
    {
        $response = $this->getJson('/api/content/v1/posts');

        $response->assertUnauthorized();
    }

    public function test_content_api_returns_published_posts(): void
    {
        $post = Post::query()->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        PostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'content_html' => '<p>Hello</p>',
            'content_plain' => 'Hello',
        ]);

        $response = $this->getJson('/api/content/v1/posts?locale=en&key=test-content-key');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'hello-world');

        $showResponse = $this->getJson('/api/content/v1/posts/hello-world?locale=en&key=test-content-key');
        $showResponse
            ->assertOk()
            ->assertJsonPath('data.title', 'Hello World');
    }
}
