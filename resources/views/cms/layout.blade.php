@php
    $cms = is_array($cmsLayout ?? null) ? $cmsLayout : [];

    $currentLocale = (string) ($cms['current_locale'] ?? config('app.locale'));
    $labels = $cms['labels'] ?? [];
    $seo = $cms['seo'] ?? [];
    $canonicalHref = $cms['canonical_href'] ?? null;
    $hreflangs = $cms['hreflangs'] ?? [];
    $robotsContent = (string) ($cms['robots_content'] ?? '');
    $customHeadHtml = $cms['custom_head_html'] ?? null;
    $structuredDataJson = $cms['structured_data_json'] ?? null;
    $isPreview = (bool) ($cms['is_preview'] ?? false);
    $chromeHeader = $cms['chrome_header'] ?? [];
    $chromeFooter = $cms['chrome_footer'] ?? [];
    $chromeSearch = $cms['chrome_search'] ?? [];
    $searchPathSlug = (string) ($cms['search_path_slug'] ?? 'search');
    $searchPlaceholder = (string) ($cms['search_placeholder'] ?? '');
    $showHeaderSearch = (bool) ($cms['show_header_search'] ?? false);
    $showFooterSearch = (bool) ($cms['show_footer_search'] ?? false);
    $headerSearchQuery = (string) ($cms['header_search_query'] ?? '');
    $localeSwitcherLinks = $cms['locale_switcher_links'] ?? [];
    $headerNavLinks = $cms['header_nav_links'] ?? [];
    $headerCtaLinks = $cms['header_cta_links'] ?? [];
    $footerLinks = $cms['footer_links'] ?? [];
    $footerSocialLinks = $cms['footer_social_links'] ?? [];
    $footerLegalLinks = $cms['footer_legal_links'] ?? [];
    $footerTagline = (string) ($cms['footer_tagline'] ?? '');
    $headerEnabled = (bool) ($cms['header_enabled'] ?? true);
    $headerVariant = (string) ($cms['header_variant'] ?? 'split_nav');
    $headerClass = (string) ($cms['header_class'] ?? 'topbar topbar-split_nav');
    $headerLeftNav = $cms['header_left_nav'] ?? [];
    $headerRightNav = $cms['header_right_nav'] ?? [];
    $footerClass = (string) ($cms['footer_class'] ?? 'site-footer footer-inline');
    $publicChrome = is_array($cms['public_chrome'] ?? null) ? $cms['public_chrome'] : [];
@endphp
<!DOCTYPE html>
<html lang="{{ $currentLocale }}">
<head>
    @include('cms.partials.head-meta')
</head>
<body class="@yield('body_class')">
{!! $publicChrome['body_start'] ?? '' !!}
<div class="site-shell">
    @include('cms.partials.chrome-header')
    @include('cms.partials.content-frame')
    @include('cms.partials.chrome-footer')
</div>
</body>
</html>
