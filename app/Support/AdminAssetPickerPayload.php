<?php

namespace App\Support;

use App\Models\Asset;
use Traversable;

class AdminAssetPickerPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromAsset(Asset $asset): array
    {
        $url = trim((string) ($asset->public_url ?? ''));
        if ($url === '' && (string) ($asset->disk ?? '') === 'public' && ! empty($asset->storage_path)) {
            $url = url('/storage/'.$asset->storage_path);
        }

        return [
            'id' => (int) $asset->id,
            'type' => (string) ($asset->type ?? ''),
            'mime_type' => (string) ($asset->mime_type ?? ''),
            'title' => (string) ($asset->title ?? ''),
            'alt' => (string) ($asset->alt ?? ''),
            'caption' => (string) ($asset->caption ?? ''),
            'credits' => (string) ($asset->credits ?? ''),
            'disk' => (string) ($asset->disk ?? ''),
            'storage_path' => (string) ($asset->storage_path ?? ''),
            'public_url' => $url,
            'size' => (int) ($asset->size ?? 0),
            'width' => $asset->width ? (int) $asset->width : null,
            'height' => $asset->height ? (int) $asset->height : null,
        ];
    }

    /**
     * @param  iterable<int, Asset>|Traversable<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    public static function collect(iterable $assets): array
    {
        $payload = [];

        foreach ($assets as $asset) {
            if (! $asset instanceof Asset) {
                continue;
            }

            $payload[] = self::fromAsset($asset);
        }

        return $payload;
    }
}
