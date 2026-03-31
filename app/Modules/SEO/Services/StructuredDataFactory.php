<?php

namespace App\Modules\SEO\Services;

use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;

class StructuredDataFactory
{
    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('seo.site.name'),
            'url' => rtrim(config('app.url'), '/'),
        ];
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
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $translation->title,
            'description' => $translation->meta_description ?? $translation->excerpt,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString(),
            'url' => $url,
            'inLanguage' => $translation->locale,
        ];
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
     * @param array<int, array<string, string>> $crumbs
     *
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
     * @param array<int, array{question: string, answer: string}> $items
     *
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
