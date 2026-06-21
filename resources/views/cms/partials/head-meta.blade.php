<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $cms['theme_color'] ?? '#0f172a' }}">
<title>{{ $seo['meta_title'] ?? config('app.name') }}</title>
@if(!empty($seo['meta_description']))
    <meta name="description" content="{{ $seo['meta_description'] }}">
@endif
@if($canonicalHref)
    <link rel="canonical" href="{{ $canonicalHref }}">
@endif
@if(!empty($hreflangs))
    @foreach($hreflangs as $localeCode => $href)
        <link rel="alternate" hreflang="{{ $localeCode }}" href="{{ $href }}">
    @endforeach
@endif
@if($robotsContent !== '')
    <meta name="robots" content="{{ $robotsContent }}">
@endif
@php
    $ogTitle = $seo['meta_title'] ?? config('seo.site.name', config('app.name'));
    $ogType = $seo['og_type'] ?? 'website';
    $ogImage = $seo['og_image'] ?? config('seo.site.organization_logo');
    $ogSiteName = config('seo.site.name', config('app.name'));
    $currentLocale = app()->getLocale();
@endphp
<meta property="og:title" content="{{ $ogTitle }}">
@if(!empty($seo['meta_description']))
    <meta property="og:description" content="{{ $seo['meta_description'] }}">
@endif
<meta property="og:type" content="{{ $ogType }}">
@if($canonicalHref)
    <meta property="og:url" content="{{ $canonicalHref }}">
@endif
<meta property="og:site_name" content="{{ $ogSiteName }}">
<meta property="og:locale" content="{{ str_replace('-', '_', $currentLocale) }}">
@foreach(($hreflangs ?? []) as $localeCode => $href)
    @if($localeCode !== 'x-default' && $localeCode !== $currentLocale)
        <meta property="og:locale:alternate" content="{{ str_replace('-', '_', $localeCode) }}">
    @endif
@endforeach
@if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endif
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
@if(!empty($seo['meta_description']))
    <meta name="twitter:description" content="{{ $seo['meta_description'] }}">
@endif
@if($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif
@if(!empty($cms['theme_google_fonts_url']))
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $cms['theme_google_fonts_url'] }}">
@endif
{!! $cms['public_chrome']['head_bootstrap'] ?? '' !!}
@include('cms.partials.theme-styles')
@stack('head')
{!! $cms['public_chrome']['head'] ?? '' !!}
@if($customHeadHtml)
    {!! $customHeadHtml !!}
@endif
@if($structuredDataJson)
    <script type="application/ld+json">{!! $structuredDataJson !!}</script>
@endif
