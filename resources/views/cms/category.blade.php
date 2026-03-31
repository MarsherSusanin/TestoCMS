@php
    $currentLocale = (string) ($locale ?? config('app.locale'));
    $isRu = $currentLocale === 'ru';
    $labels = $isRu
        ? [
            'eyebrow' => 'Рубрика',
            'posts' => 'Посты в рубрике',
            'empty' => 'В этой рубрике пока нет опубликованных постов.',
            'published' => 'Опубликовано',
            'materials' => 'материалов',
            'all_blog' => 'Весь блог',
            'page' => 'Страница',
            'per_page' => 'На странице',
            'locale' => 'Язык',
          ]
        : [
            'eyebrow' => 'Category',
            'posts' => 'Posts in category',
            'empty' => 'No published posts in this category yet.',
            'published' => 'Published',
            'materials' => 'posts',
            'all_blog' => 'All posts',
            'page' => 'Page',
            'per_page' => 'Per page',
            'locale' => 'Locale',
          ];
@endphp

@extends('cms.layout')

@section('hero')
    <div class="hero-grid">
        <div>
            <span class="eyebrow">{{ $labels['eyebrow'] }}</span>
            <h1 class="hero-title">{{ $translation->title }}</h1>
            <p class="hero-description">{{ $translation->description ?: ($seo['meta_description'] ?? '') }}</p>
            <div class="hero-actions">
                <a class="button button-primary" href="{{ url('/'.$currentLocale.'/'.config('cms.post_url_prefix')) }}">{{ $labels['all_blog'] }}</a>
            </div>
        </div>
        <aside class="hero-panel">
            <div>
                <h3>{{ $labels['posts'] }}</h3>
                <p>{{ $translation->description ?: '' }}</p>
            </div>
            <div class="hero-kpis">
                <div class="hero-kpi">
                    <strong>{{ $posts->total() }}</strong>
                    <span>{{ $labels['materials'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ $posts->currentPage() }}</strong>
                    <span>{{ $labels['page'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ strtoupper($currentLocale) }}</strong>
                    <span>{{ $labels['locale'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ $posts->perPage() }}</strong>
                    <span>{{ $labels['per_page'] }}</span>
                </div>
            </div>
        </aside>
    </div>
@endsection

@section('content')
    <section class="surface">
        <div class="surface-body">
            <div class="section-header">
                <h2>{{ $labels['posts'] }}</h2>
                <span class="muted">{{ $posts->total() }} {{ $labels['materials'] }}</span>
            </div>

            @if($posts->count() > 0)
                <div class="cards-grid">
                    @foreach($posts as $post)
                        @php $postTranslation = $post->translations->first(); @endphp
                        @continue(!$postTranslation)

                        <a class="post-card" href="{{ url('/'.$currentLocale.'/'.config('cms.post_url_prefix').'/'.$postTranslation->slug) }}">
                            <div class="meta-row" style="margin-bottom:0;">
                                <span class="tag brand">{{ strtoupper($currentLocale) }}</span>
                                <span class="tag">{{ $translation->title }}</span>
                                @if($post->published_at)
                                    <span class="tag">{{ $labels['published'] }}: {{ $post->published_at->locale($currentLocale)?->translatedFormat('d M Y') }}</span>
                                @endif
                            </div>
                            <h3 class="post-card-title">{{ $postTranslation->title }}</h3>
                            @if(!empty($postTranslation->excerpt))
                                <p class="post-card-excerpt">{{ $postTranslation->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            @else
                <div class="empty-state">{{ $labels['empty'] }}</div>
            @endif

            {{ $posts->links('cms.pagination') }}
        </div>
    </section>
@endsection
