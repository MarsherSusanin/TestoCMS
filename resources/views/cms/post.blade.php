@php
    $currentLocale = (string) ($locale ?? config('app.locale'));
    $isRu = $currentLocale === 'ru';
    $labels = $isRu
        ? [
            'article' => 'Статья',
            'published' => 'Опубликовано',
            'preview' => 'Черновик в предпросмотре',
            'back' => 'Назад в блог',
            'read_time' => 'мин чтения',
            'meta' => 'Материал сайта',
            'locale' => 'Язык',
            'status' => 'Статус',
          ]
        : [
            'article' => 'Article',
            'published' => 'Published',
            'preview' => 'Draft preview',
            'back' => 'Back to blog',
            'read_time' => 'min read',
            'meta' => 'Website article',
            'locale' => 'Locale',
            'status' => 'Status',
          ];

    $wordCount = str_word_count((string) ($translation->content_plain ?? ''));
    $readTime = max(1, (int) ceil($wordCount / 180));
@endphp

@extends('cms.layout')

@section('hero')
    <div class="hero-grid">
        <div>
            <span class="eyebrow">{{ $labels['article'] }}</span>
            <h1 class="hero-title">{{ $translation->title }}</h1>
            <p class="hero-description">{{ $translation->excerpt ?: ($seo['meta_description'] ?? $labels['meta']) }}</p>
            <div class="hero-actions">
                <a class="button button-primary" href="{{ url('/'.$currentLocale.'/'.config('cms.post_url_prefix')) }}">{{ $labels['back'] }}</a>
                <a class="button button-secondary" href="{{ url('/feed/'.$currentLocale.'.xml') }}">RSS</a>
            </div>
        </div>

        <aside class="hero-panel">
            <div>
                <h3>{{ $labels['article'] }}</h3>
                <p>{{ $labels['meta'] }}</p>
            </div>
            <div class="hero-kpis">
                <div class="hero-kpi">
                    <strong>{{ $readTime }}</strong>
                    <span>{{ $labels['read_time'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ strtoupper($currentLocale) }}</strong>
                    <span>{{ $labels['locale'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ $post->status === 'published' ? 'LIVE' : strtoupper($post->status) }}</strong>
                    <span>{{ $labels['status'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ $post->published_at?->format('d.m') ?? '--.--' }}</strong>
                    <span>{{ $labels['published'] }}</span>
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
                <span class="tag">{{ $labels['article'] }}</span>
                <span class="tag">{{ $readTime }} {{ $labels['read_time'] }}</span>
                @if($post->published_at)
                    <span class="tag">{{ $labels['published'] }}: {{ $post->published_at->locale($currentLocale)?->translatedFormat('d F Y') }}</span>
                @endif
                @if(!empty($isPreview))
                    <span class="tag brand">{{ $labels['preview'] }}</span>
                @endif
            </div>

            <div class="content-prose">
                {!! $translation->content_html !!}
            </div>
        </div>
    </article>
@endsection
