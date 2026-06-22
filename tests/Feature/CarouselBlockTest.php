<?php

namespace Tests\Feature;

use App\Modules\Content\Services\BlockSchemaValidator;
use App\Modules\Core\Contracts\BlockRendererContract;
use Tests\TestCase;

class CarouselBlockTest extends TestCase
{
    public function test_carousel_block_schema_validation_accepts_expected_payload(): void
    {
        /** @var BlockSchemaValidator $validator */
        $validator = $this->app->make(BlockSchemaValidator::class);

        $validator->validateOrFail([
            [
                'type' => 'carousel',
                'data' => [
                    'height' => 'lg',
                    'overlay_align' => 'left',
                    'overlay_theme' => 'gradient',
                    'autoplay' => false,
                    'interval_ms' => 5000,
                    'show_arrows' => true,
                    'show_dots' => true,
                    'slides' => [
                        [
                            'src' => 'https://example.com/slide-1.jpg',
                            'alt' => 'Slide 1',
                            'title' => 'Hero',
                            'text' => 'Overlay copy',
                            'cta_label' => 'Open',
                            'cta_url' => 'https://example.com/offer',
                            'target_blank' => false,
                            'nofollow' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue(true);
    }

    public function test_carousel_block_renders_public_markup_and_skips_empty_slides(): void
    {
        /** @var BlockRendererContract $renderer */
        $renderer = $this->app->make(BlockRendererContract::class);

        $html = $renderer->render([
            [
                'type' => 'carousel',
                'data' => [
                    'height' => 'xl',
                    'overlay_align' => 'center',
                    'overlay_theme' => 'dark',
                    'autoplay' => true,
                    'interval_ms' => 4500,
                    'show_arrows' => true,
                    'show_dots' => true,
                    'slides' => [
                        [
                            'src' => '',
                            'alt' => 'Skipped slide',
                            'title' => 'Skip me',
                        ],
                        [
                            'src' => 'https://example.com/slide-2.jpg',
                            'alt' => 'Second slide',
                            'title' => 'Promo slide',
                            'text' => 'Readable overlay',
                            'cta_label' => 'Buy now',
                            'cta_url' => 'https://example.com/buy',
                            'target_blank' => true,
                            'nofollow' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('cms-carousel', $html);
        $this->assertStringContainsString('data-cms-carousel', $html);
        $this->assertStringContainsString('cms-carousel-overlay', $html);
        $this->assertStringContainsString('Promo slide', $html);
        $this->assertStringContainsString('https://example.com/slide-2.jpg', $html);
        $this->assertStringContainsString('https://example.com/buy', $html);
        $this->assertSame(1, substr_count($html, 'data-cms-carousel-slide'));
    }

    public function test_carousel_block_with_no_valid_slides_is_not_rendered(): void
    {
        /** @var BlockRendererContract $renderer */
        $renderer = $this->app->make(BlockRendererContract::class);

        $html = $renderer->render([
            [
                'type' => 'carousel',
                'data' => [
                    'slides' => [
                        ['src' => '', 'alt' => 'Empty'],
                        ['src' => '   ', 'alt' => 'Empty 2'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('', trim($html));
    }
}
