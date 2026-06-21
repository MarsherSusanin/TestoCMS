<?php

namespace Tests\Feature;

use App\Modules\Content\Services\BlockLeafRendererService;
use Tests\TestCase;

class VideoEmbedBlockTest extends TestCase
{
    public function test_video_embed_allows_whitelisted_https_host_and_rejects_others(): void
    {
        config()->set('cms.custom_code.safe_embed_domains', ['youtube.com', 'vimeo.com']);
        $renderer = app(BlockLeafRendererService::class);

        $allowed = $renderer->render('video_embed', ['url' => 'https://www.youtube.com/embed/abc123', 'title' => 'Intro']);
        $this->assertStringContainsString('youtube.com/embed/abc123', $allowed);
        $this->assertStringContainsString('title="Intro"', $allowed);

        // Off-allowlist host, http scheme, and javascript: are all rejected.
        $this->assertSame('', $renderer->render('video_embed', ['url' => 'https://evil.example.com/x']));
        $this->assertSame('', $renderer->render('video_embed', ['url' => 'http://www.youtube.com/embed/abc']));
        $this->assertSame('', $renderer->render('video_embed', ['url' => 'javascript:alert(1)']));
    }
}
