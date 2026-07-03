<?php

namespace App\Modules\Content\Services;

use Illuminate\Validation\ValidationException;

class BlockSchemaValidator
{
    private const MAX_DEPTH = 3;

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function validateOrFail(array $blocks): void
    {
        $errors = [];
        $this->validateNodes($blocks, 'root', 1, false, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'content_blocks' => array_merge(['Invalid block schema.'], $errors),
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  array<int, string>  $errors
     */
    private function validateNodes(array $nodes, string $path, int $depth, bool $insideColumn, array &$errors): void
    {
        $allowedTypes = config('cms.blocks.allowed_types', []);

        if ($depth > self::MAX_DEPTH) {
            $errors[] = "Block schema path {$path} exceeds maximum nesting depth (".self::MAX_DEPTH.').';

            return;
        }

        foreach ($nodes as $index => $node) {
            $nodePath = "{$path}[{$index}]";

            if (! is_array($node)) {
                $errors[] = "Block schema path {$nodePath} must be an object.";

                continue;
            }

            $type = $node['type'] ?? null;
            if (! is_string($type) || $type === '') {
                $errors[] = "Block schema path {$nodePath} must contain a non-empty type.";

                continue;
            }

            if (! in_array($type, $allowedTypes, true)) {
                $errors[] = "Block schema path {$nodePath} has unsupported type '{$type}'.";

                continue;
            }

            $data = $node['data'] ?? [];
            if (! is_array($data)) {
                $errors[] = "Block schema path {$nodePath}.data must be an object.";

                continue;
            }

            if ($type === 'section') {
                if ($insideColumn) {
                    $errors[] = "Block schema path {$nodePath} cannot contain 'section' inside a column.";

                    continue;
                }

                $children = $node['children'] ?? null;
                if (! is_array($children)) {
                    $errors[] = "Block schema path {$nodePath}.children must be an array.";

                    continue;
                }

                $container = (string) ($data['container'] ?? 'boxed');
                if (! in_array($container, ['boxed', 'wide', 'full'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.container must be one of boxed|wide|full.";
                }
                $paddingY = (string) ($data['padding_y'] ?? 'md');
                if (! in_array($paddingY, ['none', 'sm', 'md', 'lg', 'xl'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.padding_y must be one of none|sm|md|lg|xl.";
                }
                $background = (string) ($data['background'] ?? 'none');
                if (! in_array($background, ['none', 'surface', 'brand-soft', 'custom'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.background must be one of none|surface|brand-soft|custom.";
                }

                $this->validateNodes($children, "{$nodePath}.children", $depth + 1, false, $errors);

                continue;
            }

            if ($type === 'columns') {
                if ($insideColumn) {
                    $errors[] = "Block schema path {$nodePath} cannot contain nested 'columns' inside a column.";

                    continue;
                }

                $gap = (string) ($data['gap'] ?? 'md');
                if (! in_array($gap, ['sm', 'md', 'lg'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.gap must be one of sm|md|lg.";
                }
                $alignY = (string) ($data['align_y'] ?? 'stretch');
                if (! in_array($alignY, ['start', 'center', 'end', 'stretch'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.align_y must be one of start|center|end|stretch.";
                }

                $columns = $data['columns'] ?? null;
                if (! is_array($columns)) {
                    $errors[] = "Block schema path {$nodePath}.data.columns must be an array.";

                    continue;
                }

                $count = count($columns);
                if ($count < 2 || $count > 4) {
                    $errors[] = "Block schema path {$nodePath}.data.columns must contain 2..4 columns.";
                }

                $spanSum = 0;
                foreach ($columns as $colIndex => $column) {
                    $colPath = "{$nodePath}.data.columns[{$colIndex}]";
                    if (! is_array($column)) {
                        $errors[] = "Block schema path {$colPath} must be an object.";

                        continue;
                    }

                    $span = (int) ($column['span'] ?? 0);
                    if ($span < 1 || $span > 12) {
                        $errors[] = "Block schema path {$colPath}.span must be an integer from 1 to 12.";
                    }
                    $spanSum += $span;

                    $children = $column['children'] ?? null;
                    if (! is_array($children)) {
                        $errors[] = "Block schema path {$colPath}.children must be an array.";

                        continue;
                    }

                    $this->validateNodes($children, "{$colPath}.children", $depth + 1, true, $errors);
                }

                if ($spanSum !== 12) {
                    $errors[] = "Block schema path {$nodePath}.data.columns spans must sum to 12 (got {$spanSum}).";
                }

                continue;
            }

            if ($type === 'module_widget') {
                $module = trim((string) ($data['module'] ?? ''));
                $widget = trim((string) ($data['widget'] ?? ''));
                if ($module === '') {
                    $errors[] = "Block schema path {$nodePath}.data.module must be a non-empty string.";
                }
                if ($widget === '') {
                    $errors[] = "Block schema path {$nodePath}.data.widget must be a non-empty string.";
                }
                if (array_key_exists('config', $data) && ! is_array($data['config'])) {
                    $errors[] = "Block schema path {$nodePath}.data.config must be an object.";
                }
                if (array_key_exists('children', $node)) {
                    $errors[] = "Block schema path {$nodePath} type '{$type}' does not support children.";
                }

                continue;
            }

            if ($type === 'carousel') {
                $height = (string) ($data['height'] ?? 'lg');
                if (! in_array($height, ['md', 'lg', 'xl'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.height must be one of md|lg|xl.";
                }
                $align = (string) ($data['overlay_align'] ?? 'left');
                if (! in_array($align, ['left', 'center', 'right'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.overlay_align must be one of left|center|right.";
                }
                $theme = (string) ($data['overlay_theme'] ?? 'gradient');
                if (! in_array($theme, ['gradient', 'dark', 'light'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.overlay_theme must be one of gradient|dark|light.";
                }
                $interval = (int) ($data['interval_ms'] ?? 5000);
                if ($interval < 1500 || $interval > 30000) {
                    $errors[] = "Block schema path {$nodePath}.data.interval_ms must be between 1500 and 30000.";
                }
                foreach (['autoplay', 'show_arrows', 'show_dots'] as $boolKey) {
                    if (array_key_exists($boolKey, $data) && ! is_bool($data[$boolKey])) {
                        $errors[] = "Block schema path {$nodePath}.data.{$boolKey} must be a boolean.";
                    }
                }

                $slides = $data['slides'] ?? null;
                if (! is_array($slides)) {
                    $errors[] = "Block schema path {$nodePath}.data.slides must be an array.";
                } else {
                    foreach ($slides as $slideIndex => $slide) {
                        $slidePath = "{$nodePath}.data.slides[{$slideIndex}]";
                        if (! is_array($slide)) {
                            $errors[] = "Block schema path {$slidePath} must be an object.";

                            continue;
                        }
                        foreach (['src', 'alt', 'title', 'text', 'cta_label', 'cta_url'] as $stringKey) {
                            if (array_key_exists($stringKey, $slide) && ! is_string($slide[$stringKey])) {
                                $errors[] = "Block schema path {$slidePath}.{$stringKey} must be a string.";
                            }
                        }
                        foreach (['target_blank', 'nofollow'] as $boolKey) {
                            if (array_key_exists($boolKey, $slide) && ! is_bool($slide[$boolKey])) {
                                $errors[] = "Block schema path {$slidePath}.{$boolKey} must be a boolean.";
                            }
                        }
                    }
                }
                if (array_key_exists('children', $node)) {
                    $errors[] = "Block schema path {$nodePath} type '{$type}' does not support children.";
                }

                continue;
            }

            if ($type === 'hero') {
                foreach (['heading', 'subheading', 'image', 'cta_label', 'cta_url'] as $stringKey) {
                    if (array_key_exists($stringKey, $data)) {
                        $this->checkString($data[$stringKey], "{$nodePath}.data.{$stringKey}", $errors);
                    }
                }
                $align = (string) ($data['align'] ?? 'left');
                if (! in_array($align, ['left', 'center'], true)) {
                    $errors[] = "Block schema path {$nodePath}.data.align must be one of left|center.";
                }
            }

            if (isset(self::ITEM_STRING_FIELDS[$type])) {
                $this->validateItems($type, $data, $nodePath, $errors);
            }

            if (array_key_exists('children', $node)) {
                $errors[] = "Block schema path {$nodePath} type '{$type}' does not support children.";
            }
        }
    }

    private const ITEM_STRING_FIELDS = [
        'stats' => ['value', 'label'],
        'features' => ['icon', 'title', 'text'],
        'testimonial' => ['quote', 'author', 'role'],
        'pricing' => ['name', 'price', 'period', 'cta_label', 'cta_url'],
    ];

    // Bounds so an authenticated writer can't store a multi-megabyte block that
    // is then expanded into every cached render of the page.
    private const MAX_ITEMS = 60;

    private const MAX_STRING_LENGTH = 2000;

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<int, string>  $errors
     */
    private function validateItems(string $type, array $data, string $nodePath, array &$errors): void
    {
        $items = $data['items'] ?? null;
        if (! is_array($items)) {
            $errors[] = "Block schema path {$nodePath}.data.items must be an array.";

            return;
        }

        if (count($items) > self::MAX_ITEMS) {
            $errors[] = "Block schema path {$nodePath}.data.items exceeds the maximum of ".self::MAX_ITEMS.' items.';

            return;
        }

        foreach ($items as $itemIndex => $item) {
            $itemPath = "{$nodePath}.data.items[{$itemIndex}]";
            if (! is_array($item)) {
                $errors[] = "Block schema path {$itemPath} must be an object.";

                continue;
            }

            foreach (self::ITEM_STRING_FIELDS[$type] as $stringKey) {
                if (array_key_exists($stringKey, $item)) {
                    $this->checkString($item[$stringKey], "{$itemPath}.{$stringKey}", $errors);
                }
            }

            if ($type === 'pricing' && array_key_exists('features', $item)) {
                if (! is_array($item['features'])) {
                    $errors[] = "Block schema path {$itemPath}.features must be an array.";

                    continue;
                }
                if (count($item['features']) > self::MAX_ITEMS) {
                    $errors[] = "Block schema path {$itemPath}.features exceeds the maximum of ".self::MAX_ITEMS.' entries.';

                    continue;
                }
                foreach ($item['features'] as $featureIndex => $feature) {
                    $this->checkString($feature, "{$itemPath}.features[{$featureIndex}]", $errors);
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function checkString(mixed $value, string $path, array &$errors): void
    {
        if (! is_string($value)) {
            $errors[] = "Block schema path {$path} must be a string.";

            return;
        }

        if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
            $errors[] = "Block schema path {$path} exceeds the maximum length of ".self::MAX_STRING_LENGTH.' characters.';
        }
    }
}
