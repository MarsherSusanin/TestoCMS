<?php

namespace Tests\Feature;

use App\Modules\Content\Services\BlockLeafRendererService;
use Tests\TestCase;

class ImageBlockCwvTest extends TestCase
{
    public function test_image_block_emits_width_height_for_cls(): void
    {
        $renderer = app(BlockLeafRendererService::class);

        $withDims = $renderer->render('image', ['src' => 'https://cdn.test/a.jpg', 'alt' => 'A', 'width' => 1200, 'height' => 630]);
        $this->assertStringContainsString('width="1200"', $withDims);
        $this->assertStringContainsString('height="630"', $withDims);
        $this->assertStringContainsString('decoding="async"', $withDims);

        // No dimensions provided → no bogus width/height attributes.
        $withoutDims = $renderer->render('image', ['src' => 'https://cdn.test/b.jpg', 'alt' => 'B']);
        $this->assertStringNotContainsString('width=', $withoutDims);
    }
}
