<?php

namespace App\Modules\Content\Services;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\User;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Caching\Services\PublicContentVersionService;
use App\Modules\Content\Support\LocalizedContentHelpers;
use App\Modules\Core\Contracts\ContentRevisionServiceContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Shared semantics for category forms, PUT replacement and PATCH merging. */
class CategoryContentService
{
    use LocalizedContentHelpers;

    public function __construct(
        private readonly ContentMutationGuard $guard,
        private readonly AssetUsageService $assets,
        private readonly PublicContentVersionService $version,
        private readonly PageCacheService $cache,
        private readonly ContentRevisionServiceContract $revisions,
    ) {}

    /** @return array<string, mixed> */
    public function validate(array $input, bool $patch = false, bool $web = false): array
    {
        if (! $web && isset($input['translations']) && is_array($input['translations'])) {
            foreach ($input['translations'] as &$item) {
                if (is_array($item) && isset($item['locale']) && is_string($item['locale'])) {
                    $item['locale'] = strtolower(trim($item['locale']));
                }
            }
            unset($item);
        }
        $identity = $patch || $web ? 'sometimes|nullable' : 'required';

        return Validator::make($input, [
            'parent_id' => 'sometimes|nullable|integer|exists:categories,id',
            'cover_asset_id' => 'sometimes|nullable|integer|exists:assets,id',
            'is_active' => 'sometimes|boolean',
            'translations' => ($patch ? 'sometimes' : 'required').'|array|min:1',
            'translations.*.locale' => $web ? 'prohibited' : ['required', 'string', 'max:8', 'distinct', Rule::in($this->supportedLocales())],
            'translations.*.title' => $identity.'|string|max:255',
            'translations.*.slug' => $identity.'|string|max:255',
            'translations.*.description' => 'sometimes|nullable|string',
            'translations.*.meta_title' => 'sometimes|nullable|string|max:255',
            'translations.*.meta_description' => 'sometimes|nullable|string|max:1000',
            'translations.*.canonical_url' => 'sometimes|nullable|string|max:2048',
            'translations.*.robots_directives' => 'sometimes|nullable|array',
            'translations.*.structured_data' => 'sometimes|nullable|array',
            'remove_translations' => $patch ? 'sometimes|array' : 'prohibited',
            'remove_translations.*' => ['required', 'string', 'distinct', Rule::in($this->supportedLocales())],
        ])->validate();
    }

    public function create(array $input, User $actor, bool $web = false): Category
    {
        $category = $this->mutate(null, $input, $actor, false, $web);

        return $this->finalize($category, $actor);
    }

    public function update(Category $category, array $input, User $actor, bool $patch = false, bool $web = false): Category
    {
        $category = $this->mutate($category, $input, $actor, $patch, $web);

        return $this->finalize($category, $actor);
    }

    private function mutate(?Category $entity, array $input, User $actor, bool $patch, bool $web): Category
    {
        try {
            return DB::transaction(function () use ($entity, $input, $actor, $patch, $web): Category {
                $this->guard->lockMedia();
                $this->guard->lockCategories();
                $category = $entity === null ? new Category : Category::query()->lockForUpdate()->findOrFail($entity->id);
                $current = $actor->fresh();
                abort_unless($current?->status === 'active', 403);
                Gate::forUser($current)->authorize($entity === null ? 'create' : 'update', $entity === null ? Category::class : $category);
                $parent = array_key_exists('parent_id', $input) ? $input['parent_id'] : ($patch ? $category->parent_id : null);
                $this->assertParent($category->id, $parent === null ? null : (int) $parent);
                $cover = array_key_exists('cover_asset_id', $input) ? $input['cover_asset_id'] : ($patch ? $category->cover_asset_id : null);
                $this->assets->assertReferencesAvailable(['cover_asset_id' => $cover]);
                $translations = $this->translations($category, $input, $patch, $web);
                $this->assets->assertReferencesAvailable($translations);
                $this->assertUniqueTranslationSlugs($translations, 'category_translations', 'category_id', $category->id, 'category');
                $category->fill(['parent_id' => $parent, 'cover_asset_id' => $cover,
                    'is_active' => $input['is_active'] ?? ($patch ? $category->is_active : ! $web)]);
                $category->save();
                foreach ($translations as $locale => $fields) {
                    CategoryTranslation::query()->updateOrCreate(['category_id' => $category->id, 'locale' => $locale], $fields);
                }
                $category->translations()->whereNotIn('locale', array_keys($translations))->delete();
                $this->version->bump();

                return $category->load('translations');
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Only the two category translation uniqueness constraints are user errors.
            if (! $this->isTranslationConflict($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['translations' => ['A category slug or locale already exists. Choose a unique slug for its locale.']]);
        }
    }

    private function isTranslationConflict(UniqueConstraintViolationException $exception): bool
    {
        // Inspect the driver's original error, not QueryException's appended SQL
        // and bindings: user-submitted text must not classify an unrelated PK error.
        $driver = $exception->getPrevious();
        $message = $driver instanceof \PDOException && isset($driver->errorInfo[2])
            ? (string) $driver->errorInfo[2]
            : ($driver?->getMessage() ?? '');
        if (preg_match('~unique constraint failed:\s*category_translations\.(?:locale,\s*category_translations\.slug|category_id,\s*category_translations\.locale)\s*$~iD', $message)) {
            return true;
        }

        return (bool) preg_match('~(?:for key|unique constraint)\s+[\x27"`](?:[^\x27"`]+\.)?(?:category_translations_locale_slug_unique|category_translations_category_id_locale_unique)[\x27"`]~i', $message);
    }

    /** @return array<string, array<string, mixed>> */
    private function translations(Category $category, array $input, bool $patch, bool $web): array
    {
        $previous = [];
        foreach (CategoryTranslation::query()->where('category_id', $category->id)->get() as $translation) {
            $previous[$translation->locale] = $translation->only(['title', 'slug', 'description', 'meta_title', 'meta_description', 'canonical_url', 'robots_directives', 'structured_data']);
        }
        $result = $patch ? $previous : [];
        $removed = $input['remove_translations'] ?? [];
        foreach ($removed as $locale) {
            unset($result[$locale]);
        }
        foreach ($input['translations'] ?? [] as $key => $item) {
            $locale = $web ? strtolower((string) $key) : $item['locale'];
            if (! in_array($locale, $this->supportedLocales(), true)) {
                throw ValidationException::withMessages(['translations' => ['Unsupported locale.']]);
            }
            if (in_array($locale, $removed, true)) {
                throw ValidationException::withMessages(['remove_translations' => ['A locale cannot be removed and updated together.']]);
            }
            unset($item['locale']);
            if ($web && count(array_filter($item, static fn ($value) => $value !== null && $value !== '' && $value !== [] && (! is_string($value) || trim($value) !== ''))) === 0) {
                continue;
            }
            $fields = $patch ? array_replace($previous[$locale] ?? [], $item) : $item;
            // The web form does not expose these API-only fields.
            if ($web) {
                foreach (['robots_directives', 'structured_data'] as $field) {
                    if (! array_key_exists($field, $fields)) {
                        $fields[$field] = $previous[$locale][$field] ?? null;
                    }
                }
            }
            $title = trim((string) ($fields['title'] ?? ''));
            $slug = trim(trim((string) ($fields['slug'] ?? '')), '/');
            if ($title === '' || $slug === '') {
                throw ValidationException::withMessages(["translations.$locale" => ['Title and slug are required for each translation.']]);
            }
            $this->assertSlugAllowed($slug, "translations.$locale.slug", $locale);
            $result[$locale] = ['title' => $title, 'slug' => $slug];
            foreach (['description', 'meta_title', 'meta_description', 'canonical_url'] as $field) {
                $result[$locale][$field] = $this->normalizeTextarea($fields[$field] ?? null);
            }
            foreach (['robots_directives', 'structured_data'] as $field) {
                $result[$locale][$field] = $fields[$field] ?? null;
            }
        }
        if ($result === []) {
            throw ValidationException::withMessages(['translations' => ['At least one translation must remain.']]);
        }
        if ($web) {
            $this->requireDefaultLocaleTranslation($result, ['title', 'slug']);
        }

        return $result;
    }

    public function assertParent(?int $categoryId, ?int $parentId): void
    {
        $seen = [];
        while ($parentId !== null) {
            if ($parentId === $categoryId || isset($seen[$parentId])) {
                throw ValidationException::withMessages(['parent_id' => ['Category parent would form a cycle or belongs to an existing cycle.']]);
            }
            $seen[$parentId] = true;
            $parent = Category::query()->find($parentId);
            if ($parent === null) {
                throw ValidationException::withMessages(['parent_id' => ['Parent category no longer exists.']]);
            }
            $parentId = $parent->parent_id;
        }
    }

    /** @return list<int> */
    public function descendants(int $id): array
    {
        $parents = Category::query()->pluck('parent_id', 'id')->all();
        $seen = [$id => true];
        do {
            $changed = false;
            foreach ($parents as $child => $parent) {
                if (isset($seen[$parent]) && ! isset($seen[$child])) {
                    $seen[$child] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        return array_map('intval', array_keys($seen));
    }

    /** @return list<int> */
    public function cyclicIds(): array
    {
        $parents = Category::query()->pluck('parent_id', 'id')->all();
        $cycles = [];
        foreach (array_keys($parents) as $id) {
            $chain = [];
            $cursor = $id;
            while ($cursor !== null && array_key_exists($cursor, $parents)) {
                if (isset($chain[$cursor])) {
                    foreach (array_slice(array_keys($chain), $chain[$cursor]) as $member) {
                        $cycles[$member] = true;
                    }
                    break;
                }
                $chain[$cursor] = count($chain);
                $cursor = $parents[$cursor];
            }
        }
        $ids = array_map('intval', array_keys($cycles));
        sort($ids);

        return $ids;
    }

    public function delete(Category $category, User $actor): void
    {
        DB::transaction(function () use ($category, $actor): void {
            $this->guard->lockMedia();
            $this->guard->lockCategories();
            $locked = Category::query()->lockForUpdate()->findOrFail($category->id);
            $current = $actor->fresh();
            abort_unless($current?->status === 'active', 403);
            Gate::forUser($current)->authorize('delete', $locked);
            $locked->delete();
            $this->version->bump();
        });
        $this->cache->flushAll(false);
    }

    private function finalize(Category $category, User $actor): Category
    {
        $this->revisions->snapshot('category', $category->id, $category->toArray(), $actor->id);
        $this->cache->flushAll(false);

        return $category;
    }
}
