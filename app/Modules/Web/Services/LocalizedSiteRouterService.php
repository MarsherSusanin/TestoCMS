<?php

namespace App\Modules\Web\Services;

use App\Modules\Content\Services\SlugResolverService;
use App\Modules\Core\Services\SiteChromeSettingsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizedSiteRouterService
{
    public function __construct(
        private readonly SlugResolverService $slugResolverService,
        private readonly SiteChromeSettingsService $siteChromeSettings,
        private readonly PublicSearchService $searchService,
        private readonly PublicBlogResolverService $blogResolver,
        private readonly PublicPageResolverService $pageResolver,
        private readonly PublicPostResolverService $postResolver,
        private readonly PublicCategoryResolverService $categoryResolver,
    ) {}

    public function dispatch(Request $request, string $locale, ?string $slug = null): Response
    {
        $slug = $slug === null ? 'home' : trim($slug, '/');
        $searchSlug = $this->siteChromeSettings->searchPathSlug();
        if ($slug === $searchSlug) {
            return $this->searchService->render($request, $locale);
        }

        $blogPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');

        if ($slug === $blogPrefix) {
            return $this->blogResolver->render($locale);
        }

        if (preg_match('#^'.preg_quote($blogPrefix, '#').'/page/(\\d+)$#', $slug, $matches) === 1) {
            $page = max(1, (int) ($matches[1] ?? 1));

            return $this->blogResolver->render($locale, $page);
        }

        $resolved = $this->slugResolverService->resolve($locale, $slug);

        if ($resolved === null) {
            abort(404);
        }

        return match ($resolved['type']) {
            'post' => $this->postResolver->render($locale, $resolved['model'], $resolved['translation']),
            'category' => $this->categoryResolver->render($locale, $resolved['model'], $resolved['translation']),
            default => $this->pageResolver->render($locale, $resolved['model'], $resolved['translation']),
        };
    }
}
