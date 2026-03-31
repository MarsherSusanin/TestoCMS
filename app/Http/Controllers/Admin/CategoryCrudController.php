<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithLocalizedAdminForms;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Core\Contracts\ContentRevisionServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryCrudController extends Controller
{
    use InteractsWithLocalizedAdminForms;

    public function __construct(
        private readonly ContentRevisionServiceContract $revisionService,
        private readonly AuditLogger $auditLogger,
        private readonly PageCacheService $pageCacheService,
    ) {
    }

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
        $validated = $this->validateForm($request);
        $translations = $this->normalizeTranslations($validated['translations'] ?? []);
        $this->assertUniqueTranslationSlugs($translations, 'category_translations', 'category_id', null, 'category');

        $category = DB::transaction(function () use ($request, $validated, $translations): Category {
            $category = Category::query()->create([
                'parent_id' => $validated['parent_id'] ?? null,
                'cover_asset_id' => $validated['cover_asset_id'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            $this->upsertTranslations($category, $translations);

            return $category;
        });

        $category->load('translations');
        $this->revisionService->snapshot('category', (int) $category->id, $category->toArray(), $request->user()?->id);
        $this->auditLogger->log('category.create.web', $category, [], $request);
        $this->pageCacheService->flushAll();

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
            'allCategories' => Category::query()->with('translations')->whereKeyNot($category->id)->orderByDesc('id')->get(),
            'assets' => $this->assetOptionsWithSelected($category->cover_asset_id),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $validated = $this->validateForm($request);
        $translations = $this->normalizeTranslations($validated['translations'] ?? []);
        $this->assertUniqueTranslationSlugs($translations, 'category_translations', 'category_id', (int) $category->id, 'category');

        if (isset($validated['parent_id']) && (int) $validated['parent_id'] === (int) $category->id) {
            throw ValidationException::withMessages(['parent_id' => ['Category cannot be its own parent.']]);
        }

        DB::transaction(function () use ($request, $validated, $translations, $category): void {
            $category->fill([
                'parent_id' => $validated['parent_id'] ?? null,
                'cover_asset_id' => $validated['cover_asset_id'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);
            $category->save();

            $this->upsertTranslations($category, $translations);
        });

        $category->refresh()->load('translations');
        $this->revisionService->snapshot('category', (int) $category->id, $category->toArray(), $request->user()?->id);
        $this->auditLogger->log('category.update.web', $category, [], $request);
        $this->pageCacheService->flushAll();

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Category updated.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();
        $this->auditLogger->log('category.delete.web', $category, [], $request);
        $this->pageCacheService->flushAll();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request): array
    {
        return $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
            'cover_asset_id' => 'nullable|integer|exists:assets,id',
            'is_active' => 'nullable|boolean',
            'translations' => 'required|array|min:1',
            'translations.*.title' => 'nullable|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255',
            'translations.*.description' => 'nullable|string',
            'translations.*.meta_title' => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string',
            'translations.*.canonical_url' => 'nullable|string|max:2048',
        ]);
    }

    /**
     * @param array<string, array<string, mixed>> $translationsInput
     * @return array<string, array<string, mixed>>
     */
    private function normalizeTranslations(array $translationsInput): array
    {
        $normalized = [];

        foreach ($this->supportedLocales() as $locale) {
            $item = $translationsInput[$locale] ?? [];
            if (! is_array($item)) {
                $item = [];
            }

            $title = trim((string) ($item['title'] ?? ''));
            $slug = trim((string) ($item['slug'] ?? ''));
            $description = $this->normalizeTextarea($item['description'] ?? null);
            $metaTitle = $this->normalizeTextarea($item['meta_title'] ?? null);
            $metaDescription = $this->normalizeTextarea($item['meta_description'] ?? null);
            $canonicalUrl = $this->normalizeTextarea($item['canonical_url'] ?? null);

            $usageReasons = [];
            if ($title !== '') {
                $usageReasons[] = 'title';
            }
            if ($slug !== '') {
                $usageReasons[] = 'slug';
            }
            if ($description !== null) {
                $usageReasons[] = 'description';
            }
            if ($metaTitle !== null) {
                $usageReasons[] = 'meta_title';
            }
            if ($metaDescription !== null) {
                $usageReasons[] = 'meta_description';
            }
            if ($canonicalUrl !== null) {
                $usageReasons[] = 'canonical_url';
            }

            if ($usageReasons === []) {
                continue;
            }

            if ($title === '' || $slug === '') {
                $reasonsList = implode(', ', $usageReasons);
                $message = sprintf(
                    'Locale %s contains content/settings (%s) but is missing title and/or slug.',
                    strtoupper($locale),
                    $reasonsList
                );
                throw ValidationException::withMessages([
                    "translations.{$locale}" => [$message],
                    "translations.{$locale}.title" => [$message],
                    "translations.{$locale}.slug" => [$message],
                ]);
            }

            $this->assertSlugAllowed($slug, "translations.{$locale}.slug", $locale);

            $normalized[$locale] = [
                'title' => $title,
                'slug' => trim($slug, '/'),
                'description' => $description,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'canonical_url' => $canonicalUrl,
            ];
        }

        $this->requireDefaultLocaleTranslation($normalized, ['title', 'slug']);

        return $normalized;
    }

    /**
     * @param array<string, array<string, mixed>> $translations
     */
    private function upsertTranslations(Category $category, array $translations): void
    {
        foreach ($translations as $locale => $item) {
            CategoryTranslation::query()->updateOrCreate(
                [
                    'category_id' => $category->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'description' => $item['description'],
                    'meta_title' => $item['meta_title'],
                    'meta_description' => $item['meta_description'],
                    'canonical_url' => $item['canonical_url'],
                ]
            );
        }
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
