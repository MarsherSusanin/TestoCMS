<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Api\Concerns\BuildsCacheableResponses;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Modules\Core\DTO\PageDto;
use App\Modules\Web\Services\PublicVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    use BuildsCacheableResponses;

    public function index(Request $request): JsonResponse
    {
        DB::connection()->useWriteConnectionWhenReading();
        $locale = $this->resolveLocaleFromRequest($request);
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $paginator = Page::query()
            ->published()
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = collect($paginator->items())->map(function (Page $page): array {
            $translation = $page->translations->first();

            return PageDto::fromModels($page, $translation, true)->toArray();
        })->values()->all();

        // The JSON may include dynamic listings whose modification time is
        // independent of the page; its digest ETag is the authoritative validator.

        return $this->cacheableJson($request, [
            'data' => $items,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'locale' => $locale,
            ],
        ], null);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        DB::connection()->useWriteConnectionWhenReading();
        $locale = $this->resolveLocaleFromRequest($request);

        $translation = PageTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with('page')
            ->firstOrFail();

        $page = $translation->page;
        abort_unless($page !== null && app(PublicVisibilityService::class)->isLive($page), 404);

        return $this->cacheableJson($request, [
            'data' => PageDto::fromModels($page, $translation, true)->toArray(),
        ], null);
    }
}
