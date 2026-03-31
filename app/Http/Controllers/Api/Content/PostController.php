<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Api\Concerns\BuildsCacheableResponses;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Modules\Core\DTO\PostDto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use BuildsCacheableResponses;

    public function index(Request $request): JsonResponse
    {
        $locale = $this->resolveLocaleFromRequest($request);
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $query = Post::query()
            ->published()
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'categories.translations' => fn ($q) => $q->where('locale', $locale),
            ]);

        if ($request->filled('category')) {
            $categorySlug = (string) $request->query('category');
            $query->whereHas('categories.translations', function ($q) use ($locale, $categorySlug): void {
                $q->where('locale', $locale)->where('slug', $categorySlug);
            });
        }

        $paginator = $query->orderByDesc('published_at')->paginate($perPage)->withQueryString();

        $items = collect($paginator->items())->map(function (Post $post) use ($locale): array {
            $translation = $post->translations->first() ?? $post->translations()->where('locale', config('cms.default_locale'))->first();
            $dto = PostDto::fromModels($post, $translation);

            return array_merge($dto->toArray(), [
                'categories' => $post->categories->map(static function ($category): array {
                    $translation = $category->translations->first();

                    return [
                        'id' => $category->id,
                        'title' => $translation?->title,
                        'slug' => $translation?->slug,
                    ];
                })->values()->all(),
            ]);
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

        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with(['post.categories.translations' => fn ($q) => $q->where('locale', $locale)])
            ->firstOrFail();

        $post = $translation->post;
        abort_unless($post !== null && $post->status === 'published', 404);

        $dto = PostDto::fromModels($post, $translation);

        return $this->cacheableJson($request, [
            'data' => array_merge($dto->toArray(), [
                'categories' => $post->categories->map(static function ($category): array {
                    $catTranslation = $category->translations->first();

                    return [
                        'id' => $category->id,
                        'title' => $catTranslation?->title,
                        'slug' => $catTranslation?->slug,
                    ];
                })->values()->all(),
            ]),
        ], $post->updated_at);
    }
}
