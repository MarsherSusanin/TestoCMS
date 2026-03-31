<?php

namespace App\Modules\Core\DTO;

use App\Models\Post;
use App\Models\PostTranslation;

class PostDto
{
    public function __construct(
        public int $id,
        public string $locale,
        public string $title,
        public string $slug,
        public string $status,
        public ?string $excerpt,
        public ?string $contentHtml,
        public ?string $contentPlain,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $canonicalUrl,
        public ?array $robotsDirectives,
        public ?array $structuredData,
        public ?string $publishedAt,
    ) {
    }

    public static function fromModels(Post $post, ?PostTranslation $translation): self
    {
        $locale = $translation?->locale ?? config('cms.default_locale');

        return new self(
            id: $post->id,
            locale: $locale,
            title: $translation?->title ?? 'Untitled',
            slug: $translation?->slug ?? '',
            status: $post->status,
            excerpt: $translation?->excerpt,
            contentHtml: $translation?->content_html,
            contentPlain: $translation?->content_plain,
            metaTitle: $translation?->meta_title,
            metaDescription: $translation?->meta_description,
            canonicalUrl: $translation?->canonical_url,
            robotsDirectives: $translation?->robots_directives,
            structuredData: $translation?->structured_data,
            publishedAt: $post->published_at?->toIso8601String(),
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
            'excerpt' => $this->excerpt,
            'content_html' => $this->contentHtml,
            'content_plain' => $this->contentPlain,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'canonical_url' => $this->canonicalUrl,
            'robots_directives' => $this->robotsDirectives,
            'structured_data' => $this->structuredData,
            'published_at' => $this->publishedAt,
        ];
    }
}
