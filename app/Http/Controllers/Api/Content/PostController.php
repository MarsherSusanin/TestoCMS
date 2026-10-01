<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Api\Concerns\BuildsCacheableResponses;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Modules\Core\DTO\PostDto;
use App\Modules\Web\Services\PublicVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    use BuildsCacheableResponses;

    public function index(Request $request): JsonResponse
    {
        DB::connection()->useWriteConnectionWhenReading();
        $locale = $this->resolveLocaleFromRequest($request);
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $query = Post::query()
            ->published()
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'categories' => fn ($q) => $q->where('is_active', true)->whereHas('translations', fn ($t) => $t->where('locale', $locale)),
                'categories.translations' => fn ($q) => $q->where('locale', $locale),
            ]);

        if ($request->filled('category')) {
            $categorySlug = (string) $request->query('category');
            $query->whereHas('categories', fn ($q) => $q->where('is_active', true)->whereHas('translations', fn ($t) => $t->where('locale', $locale)->where('slug', $categorySlug)));
        }

        $paginator = $query->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage)->withQueryString();

        $items = collect($paginator->items())->map(function (Post $post): array {
            $translation = $post->translations->first();
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

        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with([
                'post.categories' => fn ($q) => $q->where('is_active', true)->whereHas('translations', fn ($t) => $t->where('locale', $locale)),
                'post.categories.translations' => fn ($q) => $q->where('locale', $locale),
            ])
            ->firstOrFail();

        $post = $translation->post;
        abort_unless($post !== null && app(PublicVisibilityService::class)->isLive($post), 404);

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
        ], null);
    }
}
