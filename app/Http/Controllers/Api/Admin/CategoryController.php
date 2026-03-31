<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Core\Contracts\ContentRevisionServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function __construct(
        private readonly ContentRevisionServiceContract $revisionService,
        private readonly AuditLogger $auditLogger,
        private readonly PageCacheService $pageCacheService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $paginator = Category::query()
            ->with('translations')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateInput($request);

        $category = DB::transaction(function () use ($validated): Category {
            $category = Category::query()->create([
                'parent_id' => $validated['parent_id'] ?? null,
                'cover_asset_id' => $validated['cover_asset_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->upsertTranslations($category, $validated['translations']);

            return $category;
        });

        $category->load('translations');

        $this->revisionService->snapshot('category', $category->id, $category->toArray(), $request->user()?->id);
        $this->auditLogger->log('category.create', $category, [], $request);
        $this->pageCacheService->flushAll();

        return response()->json(['data' => $category], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('translations');

        return response()->json(['data' => $category]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $this->validateInput($request);

        DB::transaction(function () use ($category, $validated): void {
            $category->fill([
                'parent_id' => $validated['parent_id'] ?? $category->parent_id,
                'cover_asset_id' => $validated['cover_asset_id'] ?? $category->cover_asset_id,
                'is_active' => $validated['is_active'] ?? $category->is_active,
            ]);
            $category->save();

            $this->upsertTranslations($category, $validated['translations']);
        });

        $category->refresh()->load('translations');

        $this->revisionService->snapshot('category', $category->id, $category->toArray(), $request->user()?->id);
        $this->auditLogger->log('category.update', $category, [], $request);
        $this->pageCacheService->flushAll();

        return response()->json(['data' => $category]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $category->delete();

        $this->auditLogger->log('category.delete', $category, [], $request);
        $this->pageCacheService->flushAll();

        return response()->json([], 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateInput(Request $request): array
    {
        return $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
            'cover_asset_id' => 'nullable|integer|exists:assets,id',
            'is_active' => 'nullable|boolean',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|max:8',
            'translations.*.title' => 'required|string|max:255',
            'translations.*.slug' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string',
            'translations.*.meta_title' => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string',
            'translations.*.canonical_url' => 'nullable|string|max:2048',
            'translations.*.robots_directives' => 'nullable|array',
            'translations.*.structured_data' => 'nullable|array',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function upsertTranslations(Category $category, array $translations): void
    {
        foreach ($translations as $item) {
            CategoryTranslation::query()->updateOrCreate(
                [
                    'category_id' => $category->id,
                    'locale' => strtolower((string) $item['locale']),
                ],
                [
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'description' => $item['description'] ?? null,
                    'meta_title' => $item['meta_title'] ?? null,
                    'meta_description' => $item['meta_description'] ?? null,
                    'canonical_url' => $item['canonical_url'] ?? null,
                    'robots_directives' => $item['robots_directives'] ?? null,
                    'structured_data' => $item['structured_data'] ?? null,
                ]
            );
        }
    }
}
