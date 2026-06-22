<?php

namespace Tests\Feature;

use App\Modules\Content\Services\BlockLeafRendererService;
use Tests\TestCase;

class LandingBlocksTest extends TestCase
{
    private function render(string $type, array $data): string
    {
        return app(BlockLeafRendererService::class)->render($type, $data);
    }

    public function test_hero_block_renders_heading_subheading_and_cta(): void
    {
        $html = $this->render('hero', [
            'heading' => 'Launch faster',
            'subheading' => 'Build landing pages in minutes.',
            'cta_label' => 'Start',
            'cta_url' => '/en/blog',
            'align' => 'center',
        ]);

        $this->assertStringContainsString('cms-hero--align-center', $html);
        $this->assertStringContainsString('Launch faster', $html);
        $this->assertStringContainsString('Build landing pages', $html);
        $this->assertStringContainsString('href="/en/blog"', $html);

        // javascript: cta url is neutralised by safeLinkUrl.
        $evil = $this->render('hero', ['heading' => 'x', 'cta_label' => 'go', 'cta_url' => 'javascript:alert(1)']);
        $this->assertStringNotContainsString('javascript:alert(1)', $evil);
    }

    public function test_features_block_renders_cards_and_skips_empty(): void
    {
        $html = $this->render('features', ['items' => [
            ['icon' => '⚡', 'title' => 'Fast', 'text' => 'Quick.'],
            ['icon' => '', 'title' => '', 'text' => ''],
        ]]);

        $this->assertStringContainsString('cms-features', $html);
        $this->assertStringContainsString('Fast', $html);
        $this->assertSame(1, substr_count($html, 'class="cms-feature"'));
    }

    public function test_testimonial_block_renders_quote_author_role(): void
    {
        $html = $this->render('testimonial', ['items' => [
            ['quote' => 'Great product', 'author' => 'Jane', 'role' => 'CEO'],
            ['quote' => '', 'author' => 'Skip', 'role' => 'x'],
        ]]);

        $this->assertStringContainsString('Great product', $html);
        $this->assertStringContainsString('Jane', $html);
        $this->assertStringContainsString('CEO', $html);
        $this->assertSame(1, substr_count($html, 'class="cms-testimonial"'));
    }

    public function test_pricing_block_renders_tiers_with_features(): void
    {
        $html = $this->render('pricing', ['items' => [
            ['name' => 'Pro', 'price' => '$9', 'period' => 'mo', 'features' => ['A', 'B'], 'cta_label' => 'Buy', 'cta_url' => '/en/blog'],
        ]]);

        $this->assertStringContainsString('cms-pricing', $html);
        $this->assertStringContainsString('Pro', $html);
        $this->assertStringContainsString('$9', $html);
        $this->assertStringContainsString('<li>A</li>', $html);
        $this->assertStringContainsString('href="/en/blog"', $html);
    }

    public function test_empty_blocks_render_nothing(): void
    {
        $this->assertSame('', $this->render('hero', []));
        $this->assertSame('', $this->render('features', ['items' => []]));
        $this->assertSame('', $this->render('testimonial', ['items' => []]));
        $this->assertSame('', $this->render('pricing', ['items' => []]));
    }
}
