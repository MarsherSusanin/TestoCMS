<?php

namespace App\Modules\Core\DTO;

class SeoMetaDto
{
    /**
     * @param array<string, mixed>|null $robotsDirectives
     * @param array<int, array<string, mixed>>|null $structuredData
     */
    public function __construct(
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $canonicalUrl,
        public ?array $robotsDirectives,
        public ?array $structuredData,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'canonical_url' => $this->canonicalUrl,
            'robots_directives' => $this->robotsDirectives,
            'structured_data' => $this->structuredData,
        ];
    }
}
