<?php

namespace App\Modules\Content\Services;

use App\Modules\Core\Contracts\BlockRendererContract;
use Illuminate\Support\Arr;

class BlockRendererService implements BlockRendererContract
{
    public function __construct(
        private readonly BlockLeafRendererService $leafRenderer,
        private readonly BlockPreviewInstrumentationService $previewInstrumentation,
    ) {}

    public function render(array $blocks, array $context = []): string
    {
        return $this->renderNodes($blocks, $context);
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  array<string, mixed>  $context
     */
    private function renderNodes(array $nodes, array $context): string
    {
        $allowedTypes = config('cms.blocks.allowed_types', []);
        $html = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = (string) ($node['type'] ?? '');
            if (! in_array($type, $allowedTypes, true)) {
                continue;
            }

            $data = Arr::wrap($node['data'] ?? []);
            if ($type === 'section') {
                $html[] = $this->renderSection($node, $context);

                continue;
            }
            if ($type === 'columns') {
                $html[] = $this->renderColumns($node, $context);

                continue;
            }

            $leafHtml = $this->leafRenderer->render($type, $data, $context);
            $html[] = $this->previewInstrumentation->wrapLeafNode($leafHtml, $node, $context);
        }

        return implode("\n", array_filter($html));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     */
    private function renderSection(array $node, array $context): string
    {
        $data = Arr::wrap($node['data'] ?? []);
        $children = $node['children'] ?? [];
        if (! is_array($children)) {
            $children = [];
        }

        $container = (string) ($data['container'] ?? 'boxed');
        if (! in_array($container, ['boxed', 'wide', 'full'], true)) {
            $container = 'boxed';
        }

        $padding = (string) ($data['padding_y'] ?? 'md');
        if (! in_array($padding, ['none', 'sm', 'md', 'lg', 'xl'], true)) {
            $padding = 'md';
        }

        $background = (string) ($data['background'] ?? 'none');
        if (! in_array($background, ['none', 'surface', 'brand-soft', 'custom'], true)) {
            $background = 'none';
        }

        $anchorId = trim((string) ($data['anchor_id'] ?? ''));
        $anchorAttr = $anchorId !== '' ? ' id="'.e($anchorId).'"' : '';

        $styleParts = [];
        if ($background === 'custom') {
            $color = trim((string) ($data['background_color'] ?? ''));
            if ($color !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) === 1) {
                $styleParts[] = '--cms-section-bg: '.strtolower($color);
            }
        }
        $styleAttr = $styleParts !== [] ? ' style="'.e(implode('; ', $styleParts)).'"' : '';

        $innerHtml = $this->renderNodes($children, $context);
        $previewAttrs = $this->previewInstrumentation->nodeAttributes($node, 'section', $context);

        return sprintf(
            '<section class="cms-section cms-section--%s cms-section--py-%s cms-section--bg-%s"%s%s%s><div class="cms-container cms-container--%s">%s</div></section>',
            e($container),
            e($padding),
            e($background),
            $anchorAttr,
            $styleAttr,
            $previewAttrs,
            e($container),
            $innerHtml
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     */
    private function renderColumns(array $node, array $context): string
    {
        $data = Arr::wrap($node['data'] ?? []);
        $gap = (string) ($data['gap'] ?? 'md');
        if (! in_array($gap, ['sm', 'md', 'lg'], true)) {
            $gap = 'md';
        }
        $alignY = (string) ($data['align_y'] ?? 'stretch');
        if (! in_array($alignY, ['start', 'center', 'end', 'stretch'], true)) {
            $alignY = 'stretch';
        }

        $columns = $data['columns'] ?? [];
        if (! is_array($columns)) {
            $columns = [];
        }

        $colsHtml = [];
        foreach ($columns as $column) {
            if (! is_array($column)) {
                continue;
            }

            $span = (int) ($column['span'] ?? 0);
            $span = max(1, min(12, $span > 0 ? $span : 6));
            $children = $column['children'] ?? [];
            if (! is_array($children)) {
                $children = [];
            }

            $columnAttrs = $this->previewInstrumentation->columnAttributes($node, $column, $context);
            $colsHtml[] = '<div class="cms-col" style="grid-column: span '.$span.';"'.$columnAttrs.'>'.$this->renderNodes($children, $context).'</div>';
        }

        if ($colsHtml === []) {
            return '';
        }

        $previewAttrs = $this->previewInstrumentation->nodeAttributes($node, 'columns', $context);

        return '<div class="cms-columns cms-columns--gap-'.e($gap).' cms-columns--align-'.e($alignY).'"'.$previewAttrs.'>'.implode('', $colsHtml).'</div>';
    }
}
