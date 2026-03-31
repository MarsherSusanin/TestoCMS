<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Api\Concerns\BuildsCacheableResponses;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Modules\Core\DTO\AssetDto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    use BuildsCacheableResponses;

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $paginator = Asset::query()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(static fn (Asset $asset): array => AssetDto::fromModel($asset)->toArray())
            ->values()
            ->all();

        $lastModified = $paginator->getCollection()->max('updated_at');
        $lastModifiedCarbon = $lastModified instanceof Carbon ? $lastModified : null;

        return $this->cacheableJson($request, [
            'data' => $items,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], $lastModifiedCarbon);
    }
}
