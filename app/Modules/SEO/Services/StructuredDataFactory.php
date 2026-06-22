<?php

namespace App\Modules\SEO\Services;

use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Modules\Core\Services\SiteChromeSettingsService;

class StructuredDataFactory
{
    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('seo.site.name'),
            'url' => rtrim((string) config('app.url'), '/'),
        ];

        $searchTemplate = $this->siteSearchUrlTemplate();
        if ($searchTemplate !== null) {
            $node['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchTemplate,
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('seo.site.organization_name'),
            'url' => rtrim(config('app.url'), '/'),
            'logo' => config('seo.site.organization_logo'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function article(Post $post, PostTranslation $translation, string $url): array
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $translation->title,
            'description' => $translation->meta_description ?? $translation->excerpt,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString(),
            'url' => $url,
            'mainEntityOfPage' => $url,
            'inLanguage' => $translation->locale,
        ];

        $authorName = trim((string) ($post->author?->name ?? ''));
        if ($authorName !== '') {
            $node['author'] = ['@type' => 'Person', 'name' => $authorName];
        }

        $publisher = ['@type' => 'Organization', 'name' => config('seo.site.organization_name')];
        $logo = config('seo.site.organization_logo');
        if (! empty($logo)) {
            $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }
        $node['publisher'] = $publisher;

        $image = $post->featuredAsset?->public_url ?? null;
        if (! empty($image)) {
            $node['image'] = $image;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    public function webPage(PageTranslation $translation, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $translation->title,
            'url' => $url,
            'inLanguage' => $translation->locale,
            'description' => $translation->meta_description,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $crumbs): array
    {
        $items = [];

        foreach ($crumbs as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Combine schema.org nodes into a single JSON-LD graph document. Each
     * node's individual context is stripped (the graph carries one top-level
     * schema.org context). Empty nodes are dropped; returns [] when none remain.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    public function graph(array $nodes): array
    {
        $clean = [];
        foreach ($nodes as $node) {
            if (! is_array($node) || $node === []) {
                continue;
            }
            unset($node['@context']);
            $clean[] = $node;
        }

        if ($clean === []) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $clean,
        ];
    }

    /**
     * Build a FAQPage node from any `faq` blocks found in a page layout tree,
     * recursing through section/columns children. Returns null when none exist.
     *
     * @param  array<int, mixed>  $blocks
     * @return array<string, mixed>|null
     */
    public function faqFromBlocks(array $blocks): ?array
    {
        $items = [];
        $this->collectFaqItems($blocks, $items);

        return $items === [] ? null : $this->faq($items);
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  array<int, array{question: string, answer: string}>  $items
     */
    private function collectFaqItems(array $nodes, array &$items): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? '') === 'faq') {
                foreach ((array) ($node['data']['items'] ?? []) as $item) {
                    $question = trim((string) (is_array($item) ? ($item['question'] ?? '') : ''));
                    $answer = trim((string) (is_array($item) ? ($item['answer'] ?? '') : ''));
                    if ($question !== '' && $answer !== '') {
                        $items[] = ['question' => $question, 'answer' => $answer];
                    }
                }
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $this->collectFaqItems($node['children'], $items);
            }

            foreach ((array) ($node['data']['columns'] ?? []) as $column) {
                if (is_array($column) && isset($column['children']) && is_array($column['children'])) {
                    $this->collectFaqItems($column['children'], $items);
                }
            }
        }
    }

    private function siteSearchUrlTemplate(): ?string
    {
        try {
            $slug = app(SiteChromeSettingsService::class)->searchPathSlug();
        } catch (\Throwable) {
            $slug = 'search';
        }

        $slug = trim((string) $slug, '/');
        if ($slug === '') {
            return null;
        }

        $locale = strtolower((string) config('cms.default_locale', 'en'));

        return rtrim((string) config('app.url'), '/').'/'.$locale.'/'.$slug.'?q={search_term_string}';
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    public function faq(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static function (array $item): array {
                return [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ];
            }, $items),
        ];
    }
}
