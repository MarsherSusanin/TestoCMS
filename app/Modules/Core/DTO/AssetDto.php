<?php

namespace App\Modules\Core\DTO;

use App\Models\Asset;

class AssetDto
{
    public function __construct(
        public int $id,
        public string $type,
        public string $url,
        public string $mimeType,
        public int $size,
        public ?int $width,
        public ?int $height,
        public ?string $alt,
        public ?string $title,
        public ?string $caption,
    ) {}

    public static function fromModel(Asset $asset): self
    {
        return new self(
            id: $asset->id,
            type: $asset->type,
            url: $asset->public_url ?? $asset->storage_path,
            mimeType: $asset->mime_type,
            size: (int) $asset->size,
            width: $asset->width,
            height: $asset->height,
            alt: $asset->alt,
            title: $asset->title,
            caption: $asset->caption,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => $this->url,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'title' => $this->title,
            'caption' => $this->caption,
        ];
    }
}
