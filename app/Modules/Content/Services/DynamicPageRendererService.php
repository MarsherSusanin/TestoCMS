<?php

namespace App\Modules\Content\Services;

use App\Models\PageTranslation;
use App\Modules\Core\Contracts\BlockRendererContract;

class DynamicPageRendererService
{
    public function __construct(private readonly BlockRendererContract $renderer) {}

    public function hasPostListing(?PageTranslation $translation): bool
    {
        return $translation !== null && $this->containsListing((array) $translation->content_blocks);
    }

    private function containsListing(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (($node['type'] ?? '') === 'post_listing') {
                return true;
            }
            if ($this->containsListing((array) ($node['children'] ?? []))) {
                return true;
            }
            foreach ((array) ($node['data']['columns'] ?? []) as $column) {
                if (is_array($column) && $this->containsListing((array) ($column['children'] ?? []))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function render(PageTranslation $translation): string
    {
        if (! $this->hasPostListing($translation)) {
            return (string) $translation->rendered_html;
        }

        // Only layouts with a listing are rerendered; static/custom HTML pages
        // preserve the exact persisted HTML, including legacy rich content.
        return $this->renderer->render((array) $translation->content_blocks, ['locale' => $translation->locale]);
    }
}
