<?php

namespace App\Modules\Content\Services;

class BlockPreviewInstrumentationService
{
    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $context
     */
    public function wrapLeafNode(string $html, array $node, array $context): string
    {
        if ($html === '' || ! $this->isEnabled($context)) {
            return $html;
        }

        $nodeId = trim((string) ($node['id'] ?? ''));
        if ($nodeId === '') {
            return $html;
        }

        $type = trim((string) ($node['type'] ?? ''));

        return '<div class="cms-builder-node-wrapper" data-builder-node-id="'.e($nodeId).'" data-builder-node-type="'.e($type).'">'.$html.'</div>';
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $context
     */
    public function nodeAttributes(array $node, string $type, array $context): string
    {
        if (! $this->isEnabled($context)) {
            return '';
        }

        $nodeId = trim((string) ($node['id'] ?? ''));
        if ($nodeId === '') {
            return '';
        }

        return ' data-builder-node-id="'.e($nodeId).'" data-builder-node-type="'.e($type).'"';
    }

    /**
     * @param array<string, mixed> $ownerNode
     * @param array<string, mixed> $column
     * @param array<string, mixed> $context
     */
    public function columnAttributes(array $ownerNode, array $column, array $context): string
    {
        if (! $this->isEnabled($context)) {
            return '';
        }

        $columnId = trim((string) ($column['id'] ?? ''));
        if ($columnId === '') {
            return '';
        }

        $ownerNodeId = trim((string) ($ownerNode['id'] ?? ''));
        $attrs = ' data-builder-column-id="'.e($columnId).'"';
        if ($ownerNodeId !== '') {
            $attrs .= ' data-builder-owner-node-id="'.e($ownerNodeId).'"';
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function isEnabled(array $context): bool
    {
        return (($context['builder_stage_preview'] ?? false) === true)
            && (($context['instrument_nodes'] ?? false) === true);
    }
}
