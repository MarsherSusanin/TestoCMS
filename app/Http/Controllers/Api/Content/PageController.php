<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Api\Concerns\BuildsCacheableResponses;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Modules\Core\DTO\PageDto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    use BuildsCacheableResponses;

    public function index(Request $request): JsonResponse
    {
        $locale = $this->resolveLocaleFromRequest($request);
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $paginator = Page::query()
            ->published()
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();

        $items = collect($paginator->items())->map(function (Page $page): array {
            $translation = $page->translations->first() ?? $page->translations()->where('locale', config('cms.default_locale'))->first();

            return PageDto::fromModels($page, $translation)->toArray();
        })->values()->all();

        $lastModified = $paginator->getCollection()->max('updated_at');
        $lastModifiedCarbon = $lastModified instanceof Carbon ? $lastModified : null;

        return $this->cacheableJson($request, [
            'data' => $items,
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'locale' => $locale,
            ],
        ], $lastModifiedCarbon);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $this->resolveLocaleFromRequest($request);

        $translation = PageTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with('page')
            ->firstOrFail();

        $page = $translation->page;
        abort_unless($page !== null && $page->status === 'published', 404);

        return $this->cacheableJson($request, [
            'data' => PageDto::fromModels($page, $translation)->toArray(),
        ], $page->updated_at);
    }
}
