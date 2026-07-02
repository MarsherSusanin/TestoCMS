<?php

namespace Tests\Feature;

use App\Modules\Content\Services\BlockSchemaValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The landing leaf blocks must reject malformed data server-side instead of
 * storing arbitrary shapes that only the JS editors happen to produce.
 */
class LandingBlockSchemaTest extends TestCase
{
    private function validate(array $blocks): void
    {
        app(BlockSchemaValidator::class)->validateOrFail($blocks);
    }

    public function test_valid_landing_blocks_pass(): void
    {
        $this->validate([
            ['type' => 'hero', 'data' => ['heading' => 'H', 'subheading' => 'S', 'align' => 'center', 'cta_label' => 'Go', 'cta_url' => '/en']],
            ['type' => 'stats', 'data' => ['items' => [['value' => '10x', 'label' => 'Faster']]]],
            ['type' => 'features', 'data' => ['items' => [['icon' => '⚡', 'title' => 'T', 'text' => 'X']]]],
            ['type' => 'testimonial', 'data' => ['items' => [['quote' => 'Q', 'author' => 'A', 'role' => 'R']]]],
            ['type' => 'pricing', 'data' => ['items' => [['name' => 'Pro', 'price' => '$9', 'features' => ['A', 'B']]]]],
        ]);

        $this->assertTrue(true); // validateOrFail throws on violation
    }

    public function test_items_must_be_an_array(): void
    {
        $this->expectException(ValidationException::class);
        $this->validate([['type' => 'stats', 'data' => ['items' => 'not-an-array']]]);
    }

    public function test_item_fields_must_be_strings(): void
    {
        $this->expectException(ValidationException::class);
        $this->validate([['type' => 'features', 'data' => ['items' => [['title' => ['nested' => 'array']]]]]]);
    }

    public function test_pricing_features_must_be_string_list(): void
    {
        $this->expectException(ValidationException::class);
        $this->validate([['type' => 'pricing', 'data' => ['items' => [['name' => 'Pro', 'features' => [['bad' => 'shape']]]]]]]);
    }

    public function test_hero_align_and_field_types_are_enforced(): void
    {
        try {
            $this->validate([['type' => 'hero', 'data' => ['heading' => ['not' => 'string'], 'align' => 'diagonal']]]);
            $this->fail('Expected hero schema violations to throw.');
        } catch (ValidationException $e) {
            $messages = implode(' ', $e->errors()['content_blocks'] ?? []);
            $this->assertStringContainsString('heading must be a string', $messages);
            $this->assertStringContainsString('align must be one of left|center', $messages);
        }
    }

    public function test_landing_leaf_blocks_do_not_accept_children(): void
    {
        $this->expectException(ValidationException::class);
        $this->validate([['type' => 'hero', 'data' => ['heading' => 'H'], 'children' => []]]);
    }
}
