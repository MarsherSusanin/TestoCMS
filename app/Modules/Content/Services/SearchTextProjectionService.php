<?php

namespace App\Modules\Content\Services;

use App\Modules\Core\Contracts\BlockRendererContract;

/** Search the author's text, never a snapshot of other published entities. */
class SearchTextProjectionService
{
    public function __construct(private readonly BlockRendererContract $renderer) {}

    public function forPage(array $translation): string
    {
        return $this->fold(implode(' ', [
            (string) ($translation['title'] ?? ''),
            (string) ($translation['meta_description'] ?? ''),
            $this->pageText($translation),
        ]));
    }

    public function pageText(array $translation): string
    {
        $blocks = $translation['content_blocks'] ?? [];
        if (is_string($blocks)) {
            $blocks = json_decode($blocks, true) ?: [];
        }
        $html = is_array($blocks) && $blocks !== []
            ? $this->renderer->render($this->authoredNodes($blocks), ['locale' => $translation['locale'] ?? config('app.locale')])
            : (string) ($translation['rendered_html'] ?? '');

        return $this->plainText($html);
    }

    public function forPost(array $translation): string
    {
        return $this->fold(implode(' ', [
            (string) ($translation['title'] ?? ''),
            (string) ($translation['excerpt'] ?? ''),
            (string) ($translation['meta_description'] ?? ''),
            trim((string) ($translation['content_html'] ?? '')) !== ''
                ? $this->plainText((string) $translation['content_html'])
                : (string) ($translation['content_plain'] ?? ''),
        ]));
    }

    public function fold(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        return trim(preg_replace('/\s+/u', ' ', mb_convert_case($text, MB_CASE_FOLD, 'UTF-8')) ?? '');
    }

    public function plainText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#isu', ' ', $html) ?? '';
        $html = preg_replace('/<[^>]*>/u', ' ', $html) ?? '';

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function authoredNodes(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if (! is_array($node) || in_array($node['type'] ?? '', ['post_listing', 'module_widget'], true)) {
                continue;
            }
            if (is_array($node['children'] ?? null)) {
                $node['children'] = $this->authoredNodes($node['children']);
            }
            if (is_array($node['data']['columns'] ?? null)) {
                foreach ($node['data']['columns'] as &$column) {
                    if (is_array($column) && is_array($column['children'] ?? null)) {
                        $column['children'] = $this->authoredNodes($column['children']);
                    }
                }
                unset($column);
            }
            $result[] = $node;
        }

        return $result;
    }
}
