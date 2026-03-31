<?php

namespace App\Modules\Web\Services;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Post;
use App\Modules\Core\Contracts\SeoResolverContract;
use Symfony\Component\HttpFoundation\Response;

class PublicCategoryResolverService
{
    public function __construct(
        private readonly SeoResolverContract $seoResolver,
        private readonly PublicResponseSupportService $responseSupport,
    ) {
    }

    public function render(string $locale, Category $category, CategoryTranslation $translation): Response
    {
        abort_unless($category->is_active, 404);

        $posts = Post::query()
            ->published()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('published_at')
            ->paginate((int) config('cms.default_per_page', 20))
            ->withQueryString();

        $path = trim($locale.'/'.config('cms.category_url_prefix').'/'.$translation->slug, '/');
        $canonical = '/'.$path;

        $seo = $this->seoResolver->resolve('category', $category->id, $locale, [
            'meta_title' => $translation->meta_title ?? $translation->title,
            'meta_description' => $translation->meta_description ?? $translation->description,
            'canonical_url' => $translation->canonical_url ?: $canonical,
            'robots_directives' => $translation->robots_directives,
            'structured_data' => $translation->structured_data,
        ]);

        $response = response()->view('cms.category', [
            'category' => $category,
            'translation' => $translation,
            'locale' => $locale,
            'posts' => $posts,
            'seo' => $seo,
            'structuredData' => $seo['structured_data'] ?? null,
            'hreflangs' => $this->responseSupport->buildHreflangs(
                $category->translations,
                fn (string $lang, string $slug): string => '/'.$lang.'/'.config('cms.category_url_prefix').'/'.$slug
            ),
            'isPreview' => false,
        ]);

        $this->responseSupport->applyRobotsHeader($response, $seo['robots_directives'] ?? null, false);

        return $response;
    }
}
