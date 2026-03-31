<?php

namespace App\Modules\Web\Services;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Modules\Core\Contracts\SeoResolverContract;
use App\Modules\SEO\Services\StructuredDataFactory;
use Symfony\Component\HttpFoundation\Response;

class PublicPostResolverService
{
    public function __construct(
        private readonly SeoResolverContract $seoResolver,
        private readonly StructuredDataFactory $structuredDataFactory,
        private readonly PublicResponseSupportService $responseSupport,
    ) {
    }

    public function render(string $locale, Post $post, PostTranslation $translation, bool $isPreview = false): Response
    {
        if (! $isPreview && $post->status !== 'published') {
            abort(404);
        }

        $path = trim($locale.'/'.config('cms.post_url_prefix').'/'.$translation->slug, '/');
        $canonical = '/'.$path;

        $seo = $this->seoResolver->resolve('post', $post->id, $locale, [
            'meta_title' => $translation->meta_title ?? $translation->title,
            'meta_description' => $translation->meta_description ?? $translation->excerpt,
            'canonical_url' => $translation->canonical_url ?: $canonical,
            'robots_directives' => $translation->robots_directives,
            'structured_data' => $translation->structured_data,
        ]);

        $structured = $seo['structured_data'] ?? $this->structuredDataFactory->article($post, $translation, url($canonical));

        $response = response()->view('cms.post', [
            'post' => $post,
            'translation' => $translation,
            'locale' => $locale,
            'seo' => $seo,
            'structuredData' => $structured,
            'customHeadHtml' => $translation->custom_head_html ?? null,
            'hreflangs' => $this->responseSupport->buildHreflangs(
                $post->translations,
                fn (string $lang, string $slug): string => '/'.$lang.'/'.config('cms.post_url_prefix').'/'.$slug
            ),
            'isPreview' => $isPreview,
        ]);

        $this->responseSupport->applyRobotsHeader($response, $seo['robots_directives'] ?? null, $isPreview);

        return $response;
    }
}
