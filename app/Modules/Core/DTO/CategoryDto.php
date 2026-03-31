<?php

namespace App\Modules\Core\DTO;

use App\Models\Category;
use App\Models\CategoryTranslation;

class CategoryDto
{
    public function __construct(
        public int $id,
        public string $locale,
        public string $title,
        public string $slug,
        public ?string $description,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $canonicalUrl,
        public ?array $robotsDirectives,
    ) {
    }

    public static function fromModels(Category $category, ?CategoryTranslation $translation): self
    {
        $locale = $translation?->locale ?? config('cms.default_locale');

        return new self(
            id: $category->id,
            locale: $locale,
            title: $translation?->title ?? 'Untitled',
            slug: $translation?->slug ?? '',
            description: $translation?->description,
            metaTitle: $translation?->meta_title,
            metaDescription: $translation?->meta_description,
            canonicalUrl: $translation?->canonical_url,
            robotsDirectives: $translation?->robots_directives,
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
            'description' => $this->description,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'canonical_url' => $this->canonicalUrl,
            'robots_directives' => $this->robotsDirectives,
        ];
    }
}
