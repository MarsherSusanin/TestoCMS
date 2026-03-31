<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Modules\Ops\Services\AuditLogger;
use App\Support\AdminAssetPickerPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $paginator = Asset::query()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => AdminAssetPickerPayload::collect($paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'nullable|file|max:51200',
            'type' => 'nullable|string|max:32',
            'disk' => 'nullable|string|max:64',
            'storage_path' => 'nullable|string|max:2048',
            'public_url' => 'nullable|string|max:2048',
            'mime_type' => 'nullable|string|max:255',
            'size' => 'nullable|integer|min:0',
            'alt' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'credits' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $disk = $validated['disk'] ?? config('filesystems.default', 'public');
        $path = $validated['storage_path'] ?? null;
        $publicUrl = $validated['public_url'] ?? null;
        $mimeType = $validated['mime_type'] ?? 'application/octet-stream';
        $size = (int) ($validated['size'] ?? 0);
        $width = null;
        $height = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('assets', $disk);
            $publicUrl = Storage::disk($disk)->url($path);
            $mimeType = $file->getMimeType() ?? $mimeType;
            $size = $file->getSize() ?: $size;

            if (str_starts_with((string) $mimeType, 'image/')) {
                $imageSize = @getimagesize($file->getPathname());
                if (is_array($imageSize)) {
                    $width = $imageSize[0] ?? null;
                    $height = $imageSize[1] ?? null;
                }
            }
        }

        abort_if($path === null, 422, 'Either file upload or storage_path is required.');

        $asset = Asset::query()->create([
            'type' => $validated['type'] ?? $this->resolveAssetType($mimeType),
            'disk' => $disk,
            'storage_path' => $path,
            'public_url' => $publicUrl,
            'mime_type' => $mimeType,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'checksum' => null,
            'alt' => $validated['alt'] ?? null,
            'title' => $validated['title'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'credits' => $validated['credits'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        $this->auditLogger->log('asset.create', $asset, [], $request);

        return response()->json(['data' => AdminAssetPickerPayload::fromAsset($asset)], 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        return response()->json(['data' => AdminAssetPickerPayload::fromAsset($asset)]);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'alt' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'credits' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $asset->fill($validated);
        $asset->save();

        $this->auditLogger->log('asset.update', $asset, [], $request);

        return response()->json(['data' => AdminAssetPickerPayload::fromAsset($asset)]);
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        $asset->delete();

        $this->auditLogger->log('asset.delete', $asset, [], $request);

        return response()->json([], 204);
    }

    private function resolveAssetType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'document';
    }
}
