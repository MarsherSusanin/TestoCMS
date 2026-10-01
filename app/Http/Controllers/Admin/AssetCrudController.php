<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Modules\Content\Exceptions\AssetInUseException;
use App\Modules\Content\Exceptions\AssetStorageException;
use App\Modules\Content\Services\AssetDeletionService;
use App\Modules\Content\Services\AssetUsageService;
use App\Modules\Content\Services\ContentMutationGuard;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssetCrudController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly AssetDeletionService $deletion, private readonly ContentMutationGuard $guard, private readonly AssetUsageService $usage) {}

    public function index(): View
    {
        $this->authorize('viewAny', Asset::class);

        $assets = Asset::query()->orderByDesc('id')->paginate(20);

        return view('admin.assets.index', [
            'assets' => $assets,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $validated = $request->validate([
            'file' => ['nullable', 'file', 'max:'.(int) config('cms.uploads.max_kb', 51200), 'mimes:'.implode(',', (array) config('cms.uploads.allowed_extensions', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']))],
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
            $mimeType = $file->getMimeType() ?: $mimeType;
            $size = $file->getSize() ?: $size;

            if (str_starts_with((string) $mimeType, 'image/')) {
                $imageSize = @getimagesize($file->getPathname());
                if (is_array($imageSize)) {
                    $width = $imageSize[0] ?? null;
                    $height = $imageSize[1] ?? null;
                }
            }
        }

        if ($path === null) {
            return back()->withErrors(['file' => 'Upload a file or specify storage_path.'])->withInput();
        }

        $asset = DB::transaction(function () use ($validated, $disk, $path, $publicUrl, $mimeType, $size, $width, $height): Asset {
            $this->guard->lockMedia();
            $this->usage->assertReferencesAvailable(Storage::disk($disk)->url($path));

            return Asset::query()->create([
                'type' => $validated['type'] ?? $this->resolveAssetType((string) $mimeType),
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
                'metadata' => null,
            ]);

        });

        $this->auditLogger->log('asset.create.web', $asset, [], $request);

        return redirect()->route('admin.assets.edit', $asset)->with('status', 'Asset uploaded.');
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('view', $asset);

        return view('admin.assets.edit', [
            'asset' => $asset,
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $validated = $request->validate([
            'alt' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'credits' => 'nullable|string|max:255',
        ]);

        $asset = DB::transaction(function () use ($asset, $validated): Asset {
            $this->guard->lockMedia();
            $locked = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $locked->fill($validated)->save();

            return $locked;
        });

        $this->auditLogger->log('asset.update.web', $asset, [], $request);

        return redirect()->route('admin.assets.edit', $asset)->with('status', 'Asset updated.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);
        try {
            $this->deletion->delete($asset);
        } catch (AssetInUseException $exception) {
            $references = array_map(static fn (array $usage): string => $usage['entity_type'].' #'.$usage['id'].' ('.$usage['field'].') '.$usage['admin_url'], $exception->usages);

            return back()->withErrors(['asset' => $exception->getMessage().' '.implode('; ', $references)]);
        } catch (AssetStorageException $exception) {
            return back()->withErrors(['asset' => $exception->getMessage()]);
        }
        $this->auditLogger->log('asset.delete.web', $asset, [], $request);

        return redirect()->route('admin.assets.index')->with('status', 'Asset deleted.');
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
