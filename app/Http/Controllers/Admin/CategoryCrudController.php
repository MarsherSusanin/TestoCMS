<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithLocalizedAdminForms;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Modules\Content\Services\CategoryContentService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CategoryCrudController extends Controller
{
    use InteractsWithLocalizedAdminForms;

    public function __construct(private readonly CategoryContentService $content, private readonly AuditLogger $auditLogger) {}

    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with(['translations', 'parent'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('admin.categories.index', [
            'categories' => $categories,
            'locales' => $this->supportedLocales(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true]),
            'translationsByLocale' => [],
            'locales' => $this->supportedLocales(),
            'isEdit' => false,
            'allCategories' => Category::query()->with('translations')->orderByDesc('id')->get(),
            'assets' => $this->assetOptionsWithSelected(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Category::class);
        $category = $this->content->create($this->content->validate($request->all(), false, true), $request->user(), true);
        $this->auditLogger->log('category.create.web', $category, [], $request);

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('view', $category);
        $category->load('translations');

        return view('admin.categories.form', [
            'category' => $category,
            'translationsByLocale' => $this->translationsByLocale($category->translations),
            'locales' => $this->supportedLocales(),
            'isEdit' => true,
            'allCategories' => Category::query()->with('translations')->whereNotIn('id', $this->content->descendants($category->id))->orderByDesc('id')->get(),
            'assets' => $this->assetOptionsWithSelected($category->cover_asset_id),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $category = $this->content->update($category, $this->content->validate($request->all(), false, true), $request->user(), false, true);
        $this->auditLogger->log('category.update.web', $category, [], $request);

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Category updated.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->content->delete($category, $request->user());
        $this->auditLogger->log('category.delete.web', $category, [], $request);

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    /**
     * @return Collection<int, Asset>
     */
    private function assetOptionsWithSelected(?int $selectedId = null): Collection
    {
        $assets = Asset::query()->orderByDesc('id')->limit(100)->get();

        if ($selectedId !== null && $selectedId > 0 && ! $assets->contains('id', $selectedId)) {
            $selected = Asset::query()->find($selectedId);
            if ($selected !== null) {
                $assets = $assets->prepend($selected);
            }
        }

        return $assets->unique('id')->values();
    }
}
