<?php

namespace App\Modules\Content\Services;

class PageLayoutNormalizer
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalize(mixed $nodes, bool $seedEmptySection = true): array
    {
        if (! is_array($nodes)) {
            return $seedEmptySection ? $this->emptyLayout() : [];
        }

        if ($nodes === []) {
            return $seedEmptySection ? $this->emptyLayout() : [];
        }

        /** @var array<int, array<string, mixed>> $prepared */
        $prepared = array_values(array_filter($nodes, static fn (mixed $node): bool => is_array($node)));
        if ($prepared === []) {
            return $seedEmptySection ? $this->emptyLayout() : [];
        }

        if ($this->containsStructuredLayout($prepared)) {
            return $prepared;
        }

        return [$this->wrapLeafNodesInSection($prepared)];
    }

    /**
     * @param  array<int, mixed>  $nodes
     */
    public function containsStructuredLayout(array $nodes): bool
    {
        $walk = function (array $list) use (&$walk): bool {
            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $type = trim((string) ($item['type'] ?? ''));
                if ($type === 'section' || $type === 'columns') {
                    return true;
                }

                $children = $item['children'] ?? null;
                if (is_array($children) && $walk($children)) {
                    return true;
                }

                $columns = $item['data']['columns'] ?? null;
                if (is_array($columns)) {
                    foreach ($columns as $column) {
                        if (! is_array($column)) {
                            continue;
                        }
                        $columnChildren = $column['children'] ?? null;
                        if (is_array($columnChildren) && $walk($columnChildren)) {
                            return true;
                        }
                    }
                }
            }

            return false;
        };

        return $walk($nodes);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function emptyLayout(): array
    {
        return [$this->makeSection([])];
    }

    /**
     * @param  array<int, array<string, mixed>>  $leafNodes
     * @return array<string, mixed>
     */
    private function wrapLeafNodesInSection(array $leafNodes): array
    {
        return $this->makeSection($leafNodes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function makeSection(array $children): array
    {
        return [
            'type' => 'section',
            'data' => [
                'label' => 'Секция',
                'container' => 'boxed',
                'padding_y' => 'md',
                'background' => 'none',
            ],
            'children' => array_values($children),
        ];
    }
}
