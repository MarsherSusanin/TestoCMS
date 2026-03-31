@php
    $currentLocale = (string) ($locale ?? config('app.locale'));
    $isRu = $currentLocale === 'ru';
    $labels = $isRu
        ? [
            'eyebrow' => 'Блог',
            'title' => 'Публикации',
            'desc' => 'Свежие материалы, новости и заметки, подготовленные для демо-сайта TestoCMS.',
            'published' => 'Опубликовано',
            'read_more' => 'Читать',
            'empty' => 'Пока нет опубликованных постов.',
            'total' => 'материалов',
            'page' => 'Страница',
            'per_page' => 'На странице',
          ]
        : [
            'eyebrow' => 'Blog',
            'title' => 'Posts',
            'desc' => 'Published articles and updates for the TestoCMS demo website.',
            'published' => 'Published',
            'read_more' => 'Read',
            'empty' => 'No published posts yet.',
            'total' => 'posts',
            'page' => 'Page',
            'per_page' => 'Per page',
          ];

    $featuredPost = null;
    $featuredTranslation = null;
    $items = [];
    foreach ($posts as $item) {
        $translation = $item->translations->first();
        if (! $translation) {
            continue;
        }
        if ($featuredPost === null) {
            $featuredPost = $item;
            $featuredTranslation = $translation;
            continue;
        }
        $items[] = [$item, $translation];
    }
@endphp

@extends('cms.layout')

@section('hero')
    <div class="hero-grid">
        <div>
            <span class="eyebrow">{{ $labels['eyebrow'] }}</span>
            <h1 class="hero-title">{{ $labels['title'] }}</h1>
            <p class="hero-description">{{ $labels['desc'] }}</p>
        </div>
        <aside class="hero-panel">
            <div>
                <h3>{{ $labels['title'] }}</h3>
                <p>{{ $labels['desc'] }}</p>
            </div>
            <div class="hero-kpis">
                <div class="hero-kpi">
                    <strong>{{ $posts->total() }}</strong>
                    <span>{{ $labels['total'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ $posts->currentPage() }}</strong>
                    <span>{{ $labels['page'] }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ strtoupper($currentLocale) }}</strong>
                    <span>Locale</span>
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
                <h2>{{ $labels['title'] }}</h2>
                <span class="muted">{{ $posts->total() }} {{ $labels['total'] }}</span>
            </div>

            @if($featuredPost && $featuredTranslation)
                <div class="cards-grid" style="margin-bottom:14px;">
                    <a class="post-card featured" href="{{ url('/'.$locale.'/'.config('cms.post_url_prefix').'/'.$featuredTranslation->slug) }}">
                        <div class="meta-row" style="margin-bottom:0;">
                            <span class="tag brand">{{ strtoupper($locale) }}</span>
                            @if($featuredPost->published_at)
                                <span class="tag">{{ $labels['published'] }}: {{ $featuredPost->published_at->locale($currentLocale)?->translatedFormat('d F Y') }}</span>
                            @endif
                        </div>
                        <h3 class="post-card-title">{{ $featuredTranslation->title }}</h3>
                        @if(!empty($featuredTranslation->excerpt))
                            <p class="post-card-excerpt">{{ $featuredTranslation->excerpt }}</p>
                        @endif
                        <span class="button button-secondary" style="width:max-content;">{{ $labels['read_more'] }}</span>
                    </a>
                </div>
            @endif

            @if(!empty($items))
                <div class="cards-grid">
                    @foreach($items as [$post, $translation])
                        <a class="post-card" href="{{ url('/'.$locale.'/'.config('cms.post_url_prefix').'/'.$translation->slug) }}">
                            <div class="meta-row" style="margin-bottom:0;">
                                <span class="tag">{{ strtoupper($locale) }}</span>
                                @if($post->published_at)
                                    <span class="tag">{{ $post->published_at->locale($currentLocale)?->translatedFormat('d M Y') }}</span>
                                @endif
                            </div>
                            <h3 class="post-card-title">{{ $translation->title }}</h3>
                            @if(!empty($translation->excerpt))
                                <p class="post-card-excerpt">{{ $translation->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            @elseif(!$featuredPost)
                <div class="empty-state">{{ $labels['empty'] }}</div>
            @endif

            {{ $posts->links('cms.pagination') }}
        </div>
    </section>
@endsection
