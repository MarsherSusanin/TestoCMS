<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Api\Concerns\BuildsCacheableResponses;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Modules\Core\DTO\CategoryDto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use BuildsCacheableResponses;

    public function index(Request $request): JsonResponse
    {
        $locale = $this->resolveLocaleFromRequest($request);
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $paginator = Category::query()
            ->where('is_active', true)
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = collect($paginator->items())->map(function (Category $category): array {
            $translation = $category->translations->first() ?? $category->translations()->where('locale', config('cms.default_locale'))->first();

            return CategoryDto::fromModels($category, $translation)->toArray();
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

        $translation = CategoryTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with('category')
            ->firstOrFail();

        $category = $translation->category;
        abort_unless($category !== null && $category->is_active, 404);

        return $this->cacheableJson($request, [
            'data' => CategoryDto::fromModels($category, $translation)->toArray(),
        ], $category->updated_at);
    }
}
