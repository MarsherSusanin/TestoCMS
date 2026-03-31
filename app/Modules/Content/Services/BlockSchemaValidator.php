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

            if (array_key_exists('children', $node)) {
                $errors[] = "Block schema path {$nodePath} type '{$type}' does not support children.";
            }
        }
    }
}
