<?php

namespace App\Modules\Web\Services;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Modules\Core\Contracts\SeoResolverContract;
use App\Modules\SEO\Services\StructuredDataFactory;
use Symfony\Component\HttpFoundation\Response;

class PublicPageResolverService
{
    public function __construct(
        private readonly SeoResolverContract $seoResolver,
        private readonly StructuredDataFactory $structuredDataFactory,
        private readonly PublicResponseSupportService $responseSupport,
    ) {
    }

    public function render(string $locale, Page $page, PageTranslation $translation, bool $isPreview = false): Response
    {
        if (! $isPreview && $page->status !== 'published') {
            abort(404);
        }

        $path = trim($locale.'/'.$translation->slug, '/');
        $canonical = '/'.$path;

        $seo = $this->seoResolver->resolve('page', $page->id, $locale, [
            'meta_title' => $translation->meta_title ?? $translation->title,
            'meta_description' => $translation->meta_description,
            'canonical_url' => $translation->canonical_url ?: $canonical,
            'robots_directives' => $translation->robots_directives,
            'structured_data' => $translation->structured_data,
        ]);

        $structured = $seo['structured_data'] ?? $this->structuredDataFactory->webPage($translation, url($canonical));

        $response = response()->view('cms.page', [
            'page' => $page,
            'translation' => $translation,
            'locale' => $locale,
            'seo' => $seo,
            'structuredData' => $structured,
            'customHeadHtml' => $translation->custom_head_html ?? null,
            'hreflangs' => $this->responseSupport->buildHreflangs(
                $page->translations,
                fn (string $lang, string $slug): string => '/'.$lang.'/'.$slug
            ),
            'isPreview' => $isPreview,
        ]);

        $this->responseSupport->applyRobotsHeader($response, $seo['robots_directives'] ?? null, $isPreview);

        return $response;
    }
}
