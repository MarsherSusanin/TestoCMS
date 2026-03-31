@if ($paginator->hasPages())
    @php
        $isRu = app()->getLocale() === 'ru';
        $labels = $isRu
            ? [
                'prev' => 'Назад',
                'next' => 'Дальше',
                'summary' => 'Страница :page из :last',
              ]
            : [
                'prev' => 'Prev',
                'next' => 'Next',
                'summary' => 'Page :page of :last',
              ];
    @endphp

    <nav class="pager" role="navigation" aria-label="Pagination Navigation">
        <div class="pager-summary">
            {{ str_replace([':page', ':last'], [(string) $paginator->currentPage(), (string) $paginator->lastPage()], $labels['summary']) }}
        </div>

        <div class="pager-links">
            @if ($paginator->onFirstPage())
                <span class="pager-link" aria-disabled="true">{{ $labels['prev'] }}</span>
            @else
                <a class="pager-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ $labels['prev'] }}</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pager-page" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pager-page is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pager-page" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pager-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ $labels['next'] }}</a>
            @else
                <span class="pager-link" aria-disabled="true">{{ $labels['next'] }}</span>
            @endif
        </div>
    </nav>
@endif
