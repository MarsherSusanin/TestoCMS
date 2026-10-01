<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Modules\Content\Services\CategoryContentService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryContentService $content, private readonly AuditLogger $auditLogger) {}

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
        $category = $this->content->create($this->content->validate($request->all()), $request->user());
        $this->auditLogger->log('category.create', $category, [], $request);

        return response()->json(['data' => $category], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('translations');

        return response()->json(['data' => $category]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $patch = $request->isMethod('PATCH');
        $category = $this->content->update($category, $this->content->validate($request->all(), $patch), $request->user(), $patch);
        $this->auditLogger->log('category.update', $category, [], $request);

        return response()->json(['data' => $category]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->content->delete($category, $request->user());
        $this->auditLogger->log('category.delete', $category, [], $request);

        return response()->json([], 204);
    }
}
