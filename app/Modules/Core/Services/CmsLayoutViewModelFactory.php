<?php

namespace App\Modules\Core\Services;

use App\Modules\Extensibility\Registry\PublicChromeRegistry;

class CmsLayoutViewModelFactory
{
    public function __construct(
        private readonly ResolvedThemeViewModelFactory $resolvedThemeViewModelFactory,
        private readonly ResolvedChromeViewModelFactory $resolvedChromeViewModelFactory,
        private readonly PublicChromeRegistry $publicChromeRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    public function build(array $viewData): array
    {
        $themeViewModel = $this->resolvedThemeViewModelFactory->build(
            is_array($viewData['siteTheme'] ?? null) ? $viewData['siteTheme'] : null
        );
        $chromeViewModel = $this->resolvedChromeViewModelFactory->build($viewData);

        $seo = is_array($viewData['seo'] ?? null) ? $viewData['seo'] : [];
        $hreflangs = is_array($viewData['hreflangs'] ?? null) ? $viewData['hreflangs'] : [];
        $customHeadHtml = is_string($viewData['customHeadHtml'] ?? null) ? trim((string) $viewData['customHeadHtml']) : null;
        $structuredData = $viewData['structuredData'] ?? null;
        $structuredDataJson = is_array($structuredData) && $structuredData !== []
            ? json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
            : null;
        $canonicalHref = $this->safeCanonicalHref(trim((string) ($seo['canonical_url'] ?? '')));
        $robotsDirectives = is_array($seo['robots_directives'] ?? null) ? $seo['robots_directives'] : [];
        $robotsContent = collect([
            ($robotsDirectives['index'] ?? true) ? 'index' : 'noindex',
            ($robotsDirectives['follow'] ?? true) ? 'follow' : 'nofollow',
            ($robotsDirectives['noarchive'] ?? false) ? 'noarchive' : null,
            ($robotsDirectives['nosnippet'] ?? false) ? 'nosnippet' : null,
        ])->filter()->implode(',');

        $resolved = array_merge($themeViewModel, $chromeViewModel, [
            'seo' => $seo,
            'canonical_href' => $canonicalHref,
            'hreflangs' => $hreflangs,
            'robots_content' => $robotsContent,
            'custom_head_html' => $customHeadHtml,
            'structured_data_json' => $structuredDataJson,
            'is_preview' => (bool) ($viewData['isPreview'] ?? false),
        ]);

        $renderContext = array_merge($viewData, $resolved);
        $resolved['public_chrome'] = [
            'head_bootstrap' => $this->publicChromeRegistry->render('head_bootstrap', $renderContext),
            'head' => $this->publicChromeRegistry->render('head', $renderContext),
            'body_start' => $this->publicChromeRegistry->render('body_start', $renderContext),
            'header_actions' => $this->publicChromeRegistry->render('header_actions', $renderContext),
        ];

        return $resolved;
    }

    /**
     * Build a safe canonical URL. A relative value is resolved against the app
     * URL; an absolute value pointing off-site is rewritten to the app host
     * (keeping path + query) so a stored/overridden canonical can neither
     * deindex the page towards another domain nor act as an open canonical.
     */
    private function safeCanonicalHref(string $canonicalUrl): ?string
    {
        if ($canonicalUrl === '') {
            return null;
        }

        if (! str_starts_with($canonicalUrl, 'http')) {
            return url($canonicalUrl);
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST);
        if (is_string($appHost) && is_string($canonicalHost) && strcasecmp($appHost, $canonicalHost) === 0) {
            return $canonicalUrl;
        }

        $path = (string) (parse_url($canonicalUrl, PHP_URL_PATH) ?: '/');
        $query = parse_url($canonicalUrl, PHP_URL_QUERY);

        return url($path.(is_string($query) && $query !== '' ? '?'.$query : ''));
    }
}
