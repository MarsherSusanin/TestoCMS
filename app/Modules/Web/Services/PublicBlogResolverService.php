<?php

namespace App\Modules\Web\Services;

use App\Models\Post;
use Symfony\Component\HttpFoundation\Response;

class PublicBlogResolverService
{
    public function __construct(private readonly PublicResponseSupportService $responseSupport) {}

    public function render(string $locale, int $forcedPage = 1): Response
    {
        $blogPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');
        $page = max(1, $forcedPage);

        $posts = Post::query()
            ->published()
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('published_at')
            ->paginate((int) config('cms.default_per_page', 20), ['*'], 'page', $page)
            ->withQueryString();

        $canonical = '/'.trim($locale.'/'.$blogPrefix, '/');
        if ($posts->currentPage() > 1) {
            $canonical .= '?page='.$posts->currentPage();
        }

        $seo = [
            'meta_title' => ($locale === 'ru' ? 'Блог' : 'Blog').' | '.config('app.name'),
            'meta_description' => $locale === 'ru' ? 'Опубликованные материалы' : 'Published posts',
            'canonical_url' => $canonical,
            'robots_directives' => ['index' => true, 'follow' => true],
        ];

        $response = response()->view('cms.blog-index', [
            'posts' => $posts,
            'seo' => $seo,
            'structuredData' => null,
            'hreflangs' => collect(config('cms.supported_locales', ['en']))
                ->mapWithKeys(fn ($lang) => [(string) $lang => url('/'.trim((string) $lang.'/'.$blogPrefix, '/'))])
                ->all(),
            'isPreview' => false,
            'locale' => $locale,
        ]);

        $this->responseSupport->applyRobotsHeader($response, $seo['robots_directives'], false);

        return $response;
    }
}
