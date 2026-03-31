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
