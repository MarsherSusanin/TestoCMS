@extends('cms.layout')

@php
    $currentLocale = (string) ($locale ?? config('app.locale'));
    $isRu = $currentLocale === 'ru';
    $title = $isRu ? 'Поиск по сайту' : 'Site Search';
    $subtitle = $isRu
        ? 'Ищите по опубликованным страницам и постам. Результаты серверно рендерятся и индексируются.'
        : 'Search published pages and posts. Results are server-rendered and indexable.';
    $query = (string) ($searchQuery ?? '');
    $type = (string) ($searchType ?? 'all');
    $minLen = (int) ($searchMinLength ?? 2);
    $results = $searchResults ?? null;
    $count = $results ? (int) $results->total() : 0;
    $searchPathSlug = trim((string) (($searchSettings['path_slug'] ?? 'search')), '/') ?: 'search';
@endphp

@section('hero')
    <div class="hero-grid">
        <div>
            <span class="eyebrow">{{ $isRu ? 'Поиск' : 'Search' }}</span>
            <h1 class="hero-title">{{ $title }}</h1>
            <p class="hero-description">{{ $subtitle }}</p>
            <div class="hero-actions">
                <form method="GET" action="{{ url('/'.trim($currentLocale.'/'.$searchPathSlug, '/')) }}" class="site-search-form" style="min-width:min(620px, 100%);">
                    <input
                        type="search"
                        name="q"
                        value="{{ $query }}"
                        minlength="{{ $minLen }}"
                        placeholder="{{ $isRu ? 'Введите запрос…' : 'Enter query…' }}"
                    >
                    <select name="type" style="border:0; background:transparent; padding:8px 6px; color:var(--muted);">
                        <option value="all" @selected($type === 'all')>{{ $isRu ? 'Все' : 'All' }}</option>
                        <option value="posts" @selected($type === 'posts')>{{ $isRu ? 'Посты' : 'Posts' }}</option>
                        <option value="pages" @selected($type === 'pages')>{{ $isRu ? 'Страницы' : 'Pages' }}</option>
                    </select>
                    <button type="submit">{{ $isRu ? 'Найти' : 'Search' }}</button>
                </form>
            </div>
        </div>
        <div class="hero-panel">
            <h3>{{ $isRu ? 'Режим поиска' : 'Search mode' }}</h3>
            <p>
                @if($query === '')
                    {{ $isRu ? 'Введите запрос и выберите тип контента.' : 'Enter a query and choose a content type.' }}
                @elseif(mb_strlen($query) < $minLen)
                    {{ $isRu ? 'Запрос слишком короткий.' : 'Query is too short.' }}
                @else
                    {{ $isRu ? 'Найдено результатов' : 'Results found' }}: <strong>{{ $count }}</strong>
                @endif
            </p>
            <div class="hero-kpis">
                <div class="hero-kpi">
                    <strong>{{ $type }}</strong>
                    <span>{{ $isRu ? 'Тип поиска' : 'Scope' }}</span>
                </div>
                <div class="hero-kpi">
                    <strong>{{ $minLen }}</strong>
                    <span>{{ $isRu ? 'Мин. длина' : 'Min length' }}</span>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="surface">
        <div class="surface-body">
            <div class="section-header">
                <h2>{{ $isRu ? 'Результаты поиска' : 'Search Results' }}</h2>
                @if($query !== '' && mb_strlen($query) >= $minLen)
                    <span class="tag">{{ $isRu ? 'Найдено' : 'Found' }}: {{ $count }}</span>
                @endif
            </div>

            @if($query === '')
                <div class="empty-state">
                    {{ $isRu ? 'Введите запрос в форму выше. Поиск работает по опубликованным страницам и постам.' : 'Enter a query in the form above. Search works across published pages and posts.' }}
                </div>
            @elseif(mb_strlen($query) < $minLen)
                <div class="empty-state">
                    {{ $isRu ? "Минимальная длина запроса: {$minLen} символа(ов)." : "Minimum query length: {$minLen} characters." }}
                </div>
            @elseif(!$results || $results->count() === 0)
                <div class="empty-state">
                    {{ $isRu ? 'Ничего не найдено. Попробуйте другой запрос или измените тип поиска.' : 'No results found. Try another query or change the search scope.' }}
                </div>
            @else
                <div class="grid">
                    @foreach($results as $item)
                        @php
                            $itemType = (string) ($item['type'] ?? 'page');
                            $itemUrl = (string) ($item['url'] ?? '#');
                            $itemTitle = (string) ($item['title'] ?? ($isRu ? 'Без названия' : 'Untitled'));
                            $itemExcerpt = (string) ($item['excerpt'] ?? '');
                        @endphp
                        <article class="surface" style="border-radius:16px;">
                            <div class="surface-body" style="padding:14px;">
                                <div class="meta-row">
                                    <span class="tag brand">{{ strtoupper($itemType) }}</span>
                                    <a class="tag" href="{{ $itemUrl }}">{{ $isRu ? 'Открыть' : 'Open' }}</a>
                                </div>
                                <h3 class="post-card-title" style="margin-bottom:8px;">
                                    <a href="{{ $itemUrl }}" style="text-decoration:none;">{{ $itemTitle }}</a>
                                </h3>
                                @if($itemExcerpt !== '')
                                    <p class="post-card-excerpt">{{ $itemExcerpt }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                {{ $results->links('cms.pagination') }}
            @endif
        </div>
    </section>
@endsection
