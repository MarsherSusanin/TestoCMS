@php
    $currentLocale = (string) ($locale ?? config('app.locale'));
    $isRu = $currentLocale === 'ru';
    $isHome = ($translation->slug ?? '') === 'home';
    $heroSubtitle = $seo['meta_description'] ?? ($isRu ? 'Страница сайта' : 'Website page');
    $labels = $isRu
        ? [
            'landing' => 'Лендинг',
            'page' => 'Страница',
            'updated' => 'Обновлено',
            'blog' => 'К блогу',
            'admin' => 'В админку',
            'ready' => 'Готово к публикации',
            'seo' => 'SEO-first SSR',
            'i18n' => 'RU / EN',
            'hero_text' => 'Сайт работает из коробки: серверный рендер, мультиязычность и базовые редакторы уже доступны.',
            'hero_panel_title' => 'Что уже включено',
            'hero_panel_text' => 'Посты, страницы, категории, медиа, preview/schedule и техническое SEO.',
          ]
        : [
            'landing' => 'Landing',
            'page' => 'Page',
            'updated' => 'Updated',
            'blog' => 'Go to blog',
            'admin' => 'Open admin',
            'ready' => 'Ready to publish',
            'seo' => 'SEO-first SSR',
            'i18n' => 'RU / EN',
            'hero_text' => 'The site works out of the box: server-side rendering, i18n, and core editors are ready.',
            'hero_panel_title' => 'Included now',
            'hero_panel_text' => 'Posts, pages, categories, media, preview/schedule and technical SEO.',
          ];

    $renderedHtml = (string) ($translation->rendered_html ?? '');
    if ($renderedHtml !== '') {
        $updatedHtml = preg_replace('/^\s*<h1\b[^>]*>.*?<\/h1>\s*/isu', '', $renderedHtml, 1);
        if (is_string($updatedHtml)) {
            $renderedHtml = $updatedHtml;
        }
    }
@endphp

@extends('cms.layout')

@section('body_class', $isHome ? 'page-home' : 'page-standard')

@section('hero')
    <div class="hero-grid">
        <div>
            <span class="eyebrow">{{ $page->page_type === 'landing' ? $labels['landing'] : $labels['page'] }}</span>
            <h1 class="hero-title">{{ $translation->title }}</h1>
            <p class="hero-description">{{ $isHome ? $labels['hero_text'] : $heroSubtitle }}</p>

            @if($isHome)
                <div class="hero-actions">
                    <a class="button button-primary" href="{{ url('/'.$currentLocale.'/'.config('cms.post_url_prefix')) }}">{{ $labels['blog'] }}</a>
                    <a class="button button-secondary" href="{{ url('/admin') }}">{{ $labels['admin'] }}</a>
                </div>
            @endif
        </div>

        <aside class="hero-panel">
            <div>
                <h3>{{ $labels['hero_panel_title'] }}</h3>
                <p>{{ $labels['hero_panel_text'] }}</p>
            </div>
            <div class="hero-kpis">
                <div class="hero-kpi">
                    <strong>SSR</strong>
                    <span>{{ $labels['seo'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>i18n</strong>
                    <span>{{ $labels['i18n'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>CMS</strong>
                    <span>{{ $labels['ready'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ $page->status === 'published' ? 'LIVE' : strtoupper($page->status) }}</strong>
                    <span>{{ $labels['updated'] }} {{ $page->updated_at?->locale($currentLocale)?->translatedFormat('d M Y') ?? '—' }}</span>
                </div>
            </div>
        </aside>
    </div>
@endsection

@section('content')
    <article class="surface">
        <div class="surface-body">
            <div class="meta-row">
                <span class="tag brand">{{ strtoupper($currentLocale) }}</span>
                <span class="tag">{{ $page->page_type }}</span>
                @if($page->updated_at)
                    <span class="tag">{{ $labels['updated'] }}: {{ $page->updated_at->locale($currentLocale)->translatedFormat('d F Y') }}</span>
                @endif
            </div>

            <div class="content-prose">
                {!! $renderedHtml !!}
            </div>
        </div>
    </article>
@endsection
