<?php

namespace App\Modules\Web\Services;

use App\Models\PageTranslation;
use App\Models\PostTranslation;
use App\Modules\Core\Services\SiteChromeSettingsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class PublicSearchService
{
    public function __construct(
        private readonly SiteChromeSettingsService $siteChromeSettings,
        private readonly PublicResponseSupportService $responseSupport,
    ) {
    }

    public function render(Request $request, string $locale): Response
    {
        $chrome = $this->siteChromeSettings->resolvedChrome();
        $searchSettings = is_array($chrome['search'] ?? null) ? $chrome['search'] : [];
        if (($searchSettings['enabled'] ?? false) !== true) {
            abort(404);
        }

        $query = trim((string) $request->query('q', ''));
        $scope = (string) $request->query('type', (string) ($searchSettings['scope_default'] ?? 'all'));
        if (! in_array($scope, ['all', 'posts', 'pages'], true)) {
            $scope = (string) ($searchSettings['scope_default'] ?? 'all');
            if (! in_array($scope, ['all', 'posts', 'pages'], true)) {
                $scope = 'all';
            }
        }

        $minLength = max(1, (int) ($searchSettings['min_query_length'] ?? 2));
        $perPage = max(1, min(50, (int) ($searchSettings['results_per_page'] ?? 12)));
        $page = max(1, (int) $request->query('page', 1));
        $results = new LengthAwarePaginator([], 0, $perPage, $page, [
            'path' => url('/'.trim($locale.'/'.$this->siteChromeSettings->searchPathSlug(), '/')),
            'query' => $request->except('page'),
        ]);

        if ($query !== '' && mb_strlen($query) >= $minLength) {
            $results = $this->performSiteSearch($locale, $query, $scope, $perPage, $page);
            $results->appends($request->except('page'));
        }

        $searchPath = '/'.trim($locale.'/'.$this->siteChromeSettings->searchPathSlug(), '/');
        $canonical = $searchPath;
        if ($query !== '') {
            $canonical .= '?q='.rawurlencode($query).'&type='.$scope;
        }
        if ($results->currentPage() > 1) {
            $glue = str_contains($canonical, '?') ? '&' : '?';
            $canonical .= $glue.'page='.$results->currentPage();
        }

        $isRu = $locale === 'ru';
        $seo = [
            'meta_title' => ($isRu ? 'Поиск по сайту' : 'Site Search').' | '.config('app.name'),
            'meta_description' => $isRu ? 'Поиск по опубликованному контенту сайта.' : 'Search published site content.',
            'canonical_url' => $canonical,
            'robots_directives' => ['index' => true, 'follow' => true],
        ];

        $searchSlug = $this->siteChromeSettings->searchPathSlug();
        $hreflangs = collect(config('cms.supported_locales', ['ru', 'en']))
            ->mapWithKeys(function ($lang) use ($query, $scope, $searchSlug): array {
                $params = [];
                if ($query !== '') {
                    $params['q'] = $query;
                    $params['type'] = $scope;
                }
                $url = url('/'.trim((string) $lang.'/'.$searchSlug, '/'));
                if ($params !== []) {
                    $url .= '?'.http_build_query($params);
                }

                return [(string) $lang => $url];
            })
            ->all();

        $response = response()->view('cms.search', [
            'seo' => $seo,
            'structuredData' => null,
            'hreflangs' => $hreflangs,
            'isPreview' => false,
            'locale' => $locale,
            'searchQuery' => $query,
            'searchType' => $scope,
            'searchResults' => $results,
            'searchSettings' => $searchSettings,
            'searchMinLength' => $minLength,
        ]);

        $this->responseSupport->applyRobotsHeader($response, $seo['robots_directives'], false);

        return $response;
    }

    private function performSiteSearch(string $locale, string $query, string $scope, int $perPage, int $page): LengthAwarePaginator
    {
        $items = collect();

        if ($scope === 'all' || $scope === 'posts') {
            $items = $items->concat($this->searchPostItems($locale, $query));
        }
        if ($scope === 'all' || $scope === 'pages') {
            $items = $items->concat($this->searchPageItems($locale, $query));
        }

        $needle = mb_strtolower($query);
        $sorted = $items->sort(function (array $a, array $b) use ($needle): int {
            $aTitleMatch = str_contains(mb_strtolower((string) ($a['title'] ?? '')), $needle) ? 1 : 0;
            $bTitleMatch = str_contains(mb_strtolower((string) ($b['title'] ?? '')), $needle) ? 1 : 0;
            if ($aTitleMatch !== $bTitleMatch) {
                return $bTitleMatch <=> $aTitleMatch;
            }

            $aTypeScore = ($a['type'] ?? 'page') === 'post' ? 1 : 0;
            $bTypeScore = ($b['type'] ?? 'page') === 'post' ? 1 : 0;
            if ($aTypeScore !== $bTypeScore) {
                return $bTypeScore <=> $aTypeScore;
            }

            return strcmp((string) ($b['sort_at'] ?? ''), (string) ($a['sort_at'] ?? ''));
        })->values();

        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;
        $slice = $sorted->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->except('page')]
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchPostItems(string $locale, string $query): Collection
    {
        $like = '%'.$query.'%';
        $blogPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');
        $operator = $this->textSearchOperator(PostTranslation::query()->getConnection()->getDriverName());

        return PostTranslation::query()
            ->select([
                'post_translations.post_id',
                'post_translations.title',
                'post_translations.slug',
                'post_translations.excerpt',
                'post_translations.content_plain',
                'post_translations.meta_description',
                'posts.published_at',
                'posts.updated_at',
            ])
            ->join('posts', 'posts.id', '=', 'post_translations.post_id')
            ->where('post_translations.locale', $locale)
            ->where('posts.status', 'published')
            ->whereNotNull('posts.published_at')
            ->where('posts.published_at', '<=', now())
            ->where(function ($q) use ($like, $operator): void {
                $q->where('post_translations.title', $operator, $like)
                    ->orWhere('post_translations.excerpt', $operator, $like)
                    ->orWhere('post_translations.content_plain', $operator, $like)
                    ->orWhere('post_translations.meta_description', $operator, $like);
            })
            ->orderByDesc('posts.published_at')
            ->limit(500)
            ->get()
            ->map(function (PostTranslation $translation) use ($locale, $blogPrefix): array {
                $excerpt = trim((string) ($translation->excerpt ?: $translation->meta_description ?: $translation->content_plain ?: ''));

                return [
                    'type' => 'post',
                    'title' => (string) $translation->title,
                    'url' => url('/'.trim($locale.'/'.$blogPrefix.'/'.(string) $translation->slug, '/')),
                    'excerpt' => mb_substr($excerpt, 0, 220),
                    'sort_at' => (string) ($translation->getAttribute('published_at') ?: $translation->getAttribute('updated_at') ?: ''),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchPageItems(string $locale, string $query): Collection
    {
        $like = '%'.$query.'%';
        $operator = $this->textSearchOperator(PageTranslation::query()->getConnection()->getDriverName());

        return PageTranslation::query()
            ->select([
                'page_translations.page_id',
                'page_translations.title',
                'page_translations.slug',
                'page_translations.rendered_html',
                'page_translations.meta_description',
                'pages.published_at',
                'pages.updated_at',
            ])
            ->join('pages', 'pages.id', '=', 'page_translations.page_id')
            ->where('page_translations.locale', $locale)
            ->where('pages.status', 'published')
            ->whereNotNull('pages.published_at')
            ->where('pages.published_at', '<=', now())
            ->where(function ($q) use ($like, $operator): void {
                $q->where('page_translations.title', $operator, $like)
                    ->orWhere('page_translations.rendered_html', $operator, $like)
                    ->orWhere('page_translations.meta_description', $operator, $like);
            })
            ->orderByDesc('pages.updated_at')
            ->limit(500)
            ->get()
            ->map(function (PageTranslation $translation) use ($locale): array {
                $html = strip_tags((string) ($translation->rendered_html ?? ''));
                $excerpt = trim((string) ($translation->meta_description ?: $html));
                $slug = (string) $translation->slug;
                $url = $slug === 'home'
                    ? url('/'.$locale)
                    : url('/'.trim($locale.'/'.$slug, '/'));

                return [
                    'type' => 'page',
                    'title' => (string) $translation->title,
                    'url' => $url,
                    'excerpt' => mb_substr(preg_replace('/\s+/', ' ', $excerpt) ?? $excerpt, 0, 220),
                    'sort_at' => (string) ($translation->getAttribute('published_at') ?: $translation->getAttribute('updated_at') ?: ''),
                ];
            });
    }

    private function textSearchOperator(string $driver): string
    {
        return $driver === 'pgsql' ? 'ilike' : 'like';
    }
}
