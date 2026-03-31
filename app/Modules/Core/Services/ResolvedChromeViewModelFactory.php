<?php

namespace App\Modules\Core\Services;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Request;

class ResolvedChromeViewModelFactory
{
    public function __construct(
        private readonly SiteChromeSettingsService $siteChromeSettings,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    public function build(array $viewData): array
    {
        $siteChrome = is_array($viewData['siteChrome'] ?? null)
            ? $viewData['siteChrome']
            : $this->siteChromeSettings->resolvedChrome();

        $currentLocale = app()->getLocale();
        $isRu = $currentLocale === 'ru';
        $blogPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');
        $categoryPrefix = trim((string) config('cms.category_url_prefix', 'category'), '/');
        $supportedLocales = array_values(config('cms.supported_locales', ['ru', 'en']));
        $labels = $isRu
            ? [
                'home' => 'Главная',
                'blog' => 'Блог',
                'rss' => 'RSS',
                'preview' => 'Режим предпросмотра (noindex)',
                'made_with' => 'SEO-first CMS на Laravel',
                'switch_language' => 'Язык',
                'search_submit' => 'Найти',
            ]
            : [
                'home' => 'Home',
                'blog' => 'Blog',
                'rss' => 'RSS',
                'preview' => 'Preview mode (noindex)',
                'made_with' => 'SEO-first CMS on Laravel',
                'switch_language' => 'Language',
                'search_submit' => 'Search',
            ];

        $chromeHeader = is_array($siteChrome['header'] ?? null) ? $siteChrome['header'] : [];
        $chromeFooter = is_array($siteChrome['footer'] ?? null) ? $siteChrome['footer'] : [];
        $chromeSearch = is_array($siteChrome['search'] ?? null) ? $siteChrome['search'] : [];
        $searchEnabled = (bool) ($chromeSearch['enabled'] ?? false);
        $searchPathSlug = trim((string) ($chromeSearch['path_slug'] ?? 'search'), '/') ?: 'search';
        $searchPlacement = (string) ($chromeHeader['search_placement'] ?? 'header');

        $footerTagline = $this->chromeText(
            is_array($chromeFooter['tagline_translations'] ?? null) ? $chromeFooter['tagline_translations'] : null,
            $currentLocale,
            $supportedLocales
        ) ?: $labels['made_with'];

        $searchPlaceholder = $this->chromeText(
            is_array($chromeSearch['placeholder_translations'] ?? null) ? $chromeSearch['placeholder_translations'] : null,
            $currentLocale,
            $supportedLocales
        ) ?: ($isRu ? 'Поиск по сайту' : 'Search the site');

        $pathSegments = $this->request->segments();
        $tailSegments = $pathSegments;
        if (! empty($tailSegments) && in_array($tailSegments[0], $supportedLocales, true)) {
            array_shift($tailSegments);
        }
        $tailPath = implode('/', $tailSegments);
        $queryString = $this->request->getQueryString();

        $localeSwitcherLinks = [];
        if ($this->request->routeIs('site.show')) {
            foreach ($supportedLocales as $localeCode) {
                $href = url('/'.trim((string) $localeCode.($tailPath !== '' ? '/'.$tailPath : ''), '/'));
                if ($queryString) {
                    $href .= '?'.$queryString;
                }
                $localeSwitcherLinks[] = [
                    'code' => $localeCode,
                    'href' => $href,
                    'is_active' => $localeCode === $currentLocale,
                ];
            }
        }

        $isHome = $this->request->is($currentLocale) || $this->request->is($currentLocale.'/home');
        $isBlog = $this->request->is($currentLocale.'/'.$blogPrefix) || $this->request->is($currentLocale.'/'.$blogPrefix.'/*');
        $isSearch = $this->request->is($currentLocale.'/'.$searchPathSlug);

        $headerNavLinks = $this->mapChromeLinks($chromeHeader['nav_items'] ?? [], $currentLocale, $supportedLocales, $blogPrefix, $categoryPrefix);
        $headerCtaLinks = $this->mapChromeLinks($chromeHeader['cta_buttons'] ?? [], $currentLocale, $supportedLocales, $blogPrefix, $categoryPrefix);
        $footerLinks = $this->mapChromeLinks($chromeFooter['links'] ?? [], $currentLocale, $supportedLocales, $blogPrefix, $categoryPrefix);
        $footerSocialLinks = $this->mapChromeLinks($chromeFooter['social_links'] ?? [], $currentLocale, $supportedLocales, $blogPrefix, $categoryPrefix);
        $footerLegalLinks = $this->mapChromeLinks($chromeFooter['legal_links'] ?? [], $currentLocale, $supportedLocales, $blogPrefix, $categoryPrefix);

        $headerNavLinks = array_map(
            fn (array $link): array => $this->decorateNavLink($link, $currentLocale, $blogPrefix, $searchPathSlug, $isHome, $isBlog, $isSearch),
            $headerNavLinks
        );
        $headerCtaLinks = array_map(fn (array $link): array => $this->decorateLink($link), $headerCtaLinks);
        $footerLinks = array_map(fn (array $link): array => $this->decorateLink($link), $footerLinks);
        $footerSocialLinks = array_map(fn (array $link): array => $this->decorateLink($link), $footerSocialLinks);
        $footerLegalLinks = array_map(fn (array $link): array => $this->decorateLink($link), $footerLegalLinks);

        $showHeaderSearch = $searchEnabled
            && (($chromeHeader['enabled'] ?? true) === true)
            && (($chromeHeader['show_search'] ?? true) === true)
            && in_array($searchPlacement, ['header', 'both'], true);
        $showFooterSearch = $searchEnabled && in_array($searchPlacement, ['footer', 'both'], true);

        $headerEnabled = ($chromeHeader['enabled'] ?? true) === true;
        $headerVariant = (string) ($chromeHeader['variant'] ?? 'split_nav');
        $headerClass = 'topbar topbar-'.preg_replace('/[^a-z0-9_-]+/i', '', $headerVariant);
        $headerLeftNav = $headerNavLinks;
        $headerRightNav = [];
        if ($headerVariant === 'center_logo') {
            $splitAt = (int) ceil(count($headerNavLinks) / 2);
            $headerLeftNav = array_slice($headerNavLinks, 0, $splitAt);
            $headerRightNav = array_slice($headerNavLinks, $splitAt);
        }

        $footerVariant = (string) ($chromeFooter['variant'] ?? 'inline');
        $footerClass = 'site-footer footer-'.preg_replace('/[^a-z0-9_-]+/i', '', $footerVariant);

        return [
            'site_chrome' => $siteChrome,
            'current_locale' => $currentLocale,
            'supported_locales' => $supportedLocales,
            'labels' => $labels,
            'is_ru' => $isRu,
            'blog_prefix' => $blogPrefix,
            'category_prefix' => $categoryPrefix,
            'chrome_header' => $chromeHeader,
            'chrome_footer' => $chromeFooter,
            'chrome_search' => $chromeSearch,
            'search_path_slug' => $searchPathSlug,
            'search_placeholder' => $searchPlaceholder,
            'show_header_search' => $showHeaderSearch,
            'show_footer_search' => $showFooterSearch,
            'search_scope_default' => (string) ($chromeSearch['scope_default'] ?? 'all'),
            'search_min_length' => (int) ($chromeSearch['min_query_length'] ?? 2),
            'header_search_query' => $this->request->routeIs('site.search') ? (string) $this->request->query('q', '') : '',
            'search_submit_label' => $labels['search_submit'],
            'locale_switcher_links' => $localeSwitcherLinks,
            'header_nav_links' => $headerNavLinks,
            'header_cta_links' => $headerCtaLinks,
            'footer_links' => $footerLinks,
            'footer_social_links' => $footerSocialLinks,
            'footer_legal_links' => $footerLegalLinks,
            'footer_tagline' => $footerTagline,
            'header_enabled' => $headerEnabled,
            'header_variant' => $headerVariant,
            'header_class' => $headerClass,
            'header_left_nav' => $headerLeftNav,
            'header_right_nav' => $headerRightNav,
            'footer_class' => $footerClass,
            'is_home' => $isHome,
            'is_blog' => $isBlog,
            'is_search' => $isSearch,
        ];
    }

    private function chromeText(?array $translations, string $locale, array $fallbackLocales): string
    {
        $translations = is_array($translations) ? $translations : [];
        $candidate = trim((string) ($translations[$locale] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
        foreach ($fallbackLocales as $fallbackLocale) {
            $value = trim((string) ($translations[$fallbackLocale] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        foreach ($translations as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function chromeResolveHref(string $urlTemplate, string $locale): string
    {
        $urlTemplate = str_replace('{locale}', $locale, trim($urlTemplate));
        if ($urlTemplate === '') {
            return '#';
        }
        if (preg_match('/^(https?:|mailto:|tel:)/i', $urlTemplate) === 1) {
            return $urlTemplate;
        }
        if (str_starts_with($urlTemplate, '/')) {
            return url($urlTemplate);
        }
        if (str_starts_with($urlTemplate, '#') || str_starts_with($urlTemplate, '?')) {
            return $urlTemplate;
        }

        return url('/'.$urlTemplate);
    }

    /**
     * @param  array<int, mixed>|null  $items
     * @param  array<int, string>  $supportedLocales
     * @return array<int, array<string, mixed>>
     */
    private function mapChromeLinks(?array $items, string $locale, array $supportedLocales, string $blogPrefix, string $categoryPrefix): array
    {
        $items = is_array($items) ? $items : [];
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || (($item['enabled'] ?? false) !== true)) {
                continue;
            }
            $resolvedTarget = $this->resolveEntityLink($item['link_target'] ?? null, $locale, $supportedLocales, $blogPrefix, $categoryPrefix);
            $itemLabels = is_array($item['label_translations'] ?? null) ? $item['label_translations'] : [];
            $targetLabels = is_array($resolvedTarget['label_translations'] ?? null) ? $resolvedTarget['label_translations'] : [];
            $mergedLabels = [];
            foreach ($supportedLocales as $localeCode) {
                $custom = trim((string) ($itemLabels[$localeCode] ?? ''));
                $auto = trim((string) ($targetLabels[$localeCode] ?? ''));
                $mergedLabels[$localeCode] = $custom !== '' ? $custom : $auto;
            }
            $label = $this->chromeText($mergedLabels, $locale, $supportedLocales);
            if ($label === '') {
                continue;
            }
            $fallbackHref = $this->chromeResolveHref((string) ($item['url'] ?? ''), $locale);
            $out[] = [
                'label' => $label,
                'href' => (string) ($resolvedTarget['href'] ?? $fallbackHref),
                'new_tab' => (bool) ($item['new_tab'] ?? false),
                'nofollow' => (bool) ($item['nofollow'] ?? false),
                'style' => (string) ($item['style'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $target
     * @param  array<int, string>  $supportedLocales
     * @return array<string, mixed>|null
     */
    private function resolveEntityLink($target, string $requestedLocale, array $supportedLocales, string $blogPrefix, string $categoryPrefix): ?array
    {
        if (! is_array($target)) {
            return null;
        }
        $type = strtolower(trim((string) ($target['type'] ?? '')));
        $id = (int) ($target['id'] ?? 0);
        if (! in_array($type, ['page', 'post', 'category'], true) || $id <= 0) {
            return null;
        }

        $record = match ($type) {
            'page' => Page::query()->with('translations')->find($id),
            'post' => Post::query()->with('translations')->find($id),
            default => Category::query()->with('translations')->find($id),
        };
        if ($record === null) {
            return null;
        }

        if ($type === 'category') {
            if (($record->is_active ?? false) !== true) {
                return null;
            }
        } else {
            if (($record->status ?? null) !== 'published') {
                return null;
            }
            $publishedAt = $record->published_at ?? null;
            if ($publishedAt !== null && method_exists($publishedAt, 'isFuture') && $publishedAt->isFuture()) {
                return null;
            }
        }

        $titleTranslations = [];
        $slugTranslations = [];
        foreach ($record->translations ?? [] as $translation) {
            $localeCode = strtolower(trim((string) ($translation->locale ?? '')));
            if ($localeCode === '') {
                continue;
            }
            $titleTranslations[$localeCode] = trim((string) ($translation->title ?? ''));
            $slugTranslations[$localeCode] = trim((string) ($translation->slug ?? ''));
        }

        $defaultSiteLocale = strtolower((string) config('cms.default_locale', 'ru'));
        $orderedLocales = array_values(array_unique(array_filter(array_merge(
            [$requestedLocale, $defaultSiteLocale],
            $supportedLocales
        ))));

        $resolvedLocale = null;
        $resolvedSlug = '';
        foreach ($orderedLocales as $localeCode) {
            $candidate = trim((string) ($slugTranslations[$localeCode] ?? ''));
            if ($candidate !== '') {
                $resolvedLocale = $localeCode;
                $resolvedSlug = $candidate;
                break;
            }
        }
        if ($resolvedSlug === '') {
            foreach ($slugTranslations as $localeCode => $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    $resolvedLocale = (string) $localeCode;
                    $resolvedSlug = $candidate;
                    break;
                }
            }
        }
        if ($resolvedSlug === '' || $resolvedLocale === null) {
            return null;
        }

        $path = match ($type) {
            'page' => $resolvedSlug === 'home'
                ? '/'.trim($resolvedLocale, '/')
                : '/'.trim($resolvedLocale.'/'.$resolvedSlug, '/'),
            'post' => '/'.trim($resolvedLocale.'/'.$blogPrefix.'/'.$resolvedSlug, '/'),
            default => '/'.trim($resolvedLocale.'/'.$categoryPrefix.'/'.$resolvedSlug, '/'),
        };

        return [
            'href' => url($path),
            'label_translations' => $titleTranslations,
        ];
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>
     */
    private function decorateLink(array $link): array
    {
        $rel = [];
        if (! empty($link['nofollow'])) {
            $rel[] = 'nofollow';
        }
        if (! empty($link['new_tab'])) {
            $rel[] = 'noopener';
            $rel[] = 'noreferrer';
        }

        $link['rel'] = $rel !== [] ? implode(' ', array_values(array_unique($rel))) : null;
        $link['target_blank'] = ! empty($link['new_tab']);

        return $link;
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>
     */
    private function decorateNavLink(
        array $link,
        string $currentLocale,
        string $blogPrefix,
        string $searchPathSlug,
        bool $isHome,
        bool $isBlog,
        bool $isSearch
    ): array {
        $link = $this->decorateLink($link);
        $hrefPath = (string) (parse_url($link['href'] ?? '', PHP_URL_PATH) ?? '');
        $link['is_active'] = ($isHome && $hrefPath === '/'.$currentLocale)
            || ($isBlog && $hrefPath === '/'.$currentLocale.'/'.$blogPrefix)
            || ($isSearch && $hrefPath === '/'.$currentLocale.'/'.$searchPathSlug);

        return $link;
    }
}
