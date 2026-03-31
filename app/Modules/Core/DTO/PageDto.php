<?php

namespace App\Modules\Core\DTO;

use App\Models\Page;
use App\Models\PageTranslation;

class PageDto
{
    public function __construct(
        public int $id,
        public string $locale,
        public string $title,
        public string $slug,
        public string $status,
        public string $pageType,
        public array $blocks,
        public ?string $renderedHtml,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $canonicalUrl,
        public ?array $robotsDirectives,
        public ?array $structuredData,
        public ?string $publishedAt,
    ) {
    }

    public static function fromModels(Page $page, ?PageTranslation $translation): self
    {
        $locale = $translation?->locale ?? config('cms.default_locale');

        return new self(
            id: $page->id,
            locale: $locale,
            title: $translation?->title ?? 'Untitled',
            slug: $translation?->slug ?? '',
            status: $page->status,
            pageType: $page->page_type,
            blocks: $translation?->content_blocks ?? [],
            renderedHtml: $translation?->rendered_html,
            metaTitle: $translation?->meta_title,
            metaDescription: $translation?->meta_description,
            canonicalUrl: $translation?->canonical_url,
            robotsDirectives: $translation?->robots_directives,
            structuredData: $translation?->structured_data,
            publishedAt: $page->published_at?->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'page_type' => $this->pageType,
            'content_blocks' => $this->blocks,
            'rendered_html' => $this->renderedHtml,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'canonical_url' => $this->canonicalUrl,
            'robots_directives' => $this->robotsDirectives,
            'structured_data' => $this->structuredData,
            'published_at' => $this->publishedAt,
        ];
    }
}
