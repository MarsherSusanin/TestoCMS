@if($headerEnabled)
    <header class="{{ $headerClass }}">
        @if($headerVariant === 'center_logo')
            <div class="topbar-right" data-side="left">
                @if(!empty($headerLeftNav))
                    <nav class="nav-pills" aria-label="Primary">
                        @foreach($headerLeftNav as $nav)
                            <a class="nav-pill {{ !empty($nav['is_active']) ? 'is-active' : '' }}"
                               href="{{ $nav['href'] }}"
                               @if(!empty($nav['target_blank'])) target="_blank" @endif
                               @if(!empty($nav['rel'])) rel="{{ $nav['rel'] }}" @endif>{{ $nav['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        @endif

        <a class="brand" href="{{ url('/'.$currentLocale) }}">
            <span class="brand-mark" aria-hidden="true"></span>
            <span class="brand-copy">
                <p class="brand-title">{{ config('app.name') }}</p>
                @if(($chromeHeader['show_brand_subtitle'] ?? true) === true)
                    <p class="brand-subtitle">{{ $footerTagline }}</p>
                @endif
            </span>
        </a>

        <div class="topbar-right" data-side="right">
            @if($headerVariant !== 'center_logo' && !empty($headerNavLinks))
                <nav class="nav-pills" aria-label="Primary">
                    @foreach($headerNavLinks as $nav)
                        <a class="nav-pill {{ !empty($nav['is_active']) ? 'is-active' : '' }}"
                           href="{{ $nav['href'] }}"
                           @if(!empty($nav['target_blank'])) target="_blank" @endif
                           @if(!empty($nav['rel'])) rel="{{ $nav['rel'] }}" @endif>{{ $nav['label'] }}</a>
                    @endforeach
                </nav>
            @elseif($headerVariant === 'center_logo' && !empty($headerRightNav))
                <nav class="nav-pills" aria-label="Primary">
                    @foreach($headerRightNav as $nav)
                        <a class="nav-pill {{ !empty($nav['is_active']) ? 'is-active' : '' }}"
                           href="{{ $nav['href'] }}"
                           @if(!empty($nav['target_blank'])) target="_blank" @endif
                           @if(!empty($nav['rel'])) rel="{{ $nav['rel'] }}" @endif>{{ $nav['label'] }}</a>
                    @endforeach
                </nav>
            @endif

            @foreach($headerCtaLinks as $ctaLink)
                @php
                    $ctaStyle = in_array(($ctaLink['style'] ?? ''), ['primary', 'secondary', 'ghost'], true)
                        ? 'button-'.$ctaLink['style']
                        : 'button-primary';
                @endphp
                <a class="button {{ $ctaStyle }}"
                   href="{{ $ctaLink['href'] }}"
                   @if(!empty($ctaLink['target_blank'])) target="_blank" @endif
                   @if(!empty($ctaLink['rel'])) rel="{{ $ctaLink['rel'] }}" @endif>{{ $ctaLink['label'] }}</a>
            @endforeach

            @if($showHeaderSearch)
                <div class="site-search-inline">
                    @include('cms.partials.search-shell', [
                        'action' => url('/'.trim($currentLocale.'/'.$searchPathSlug, '/')),
                        'value' => $headerSearchQuery,
                        'placeholder' => $searchPlaceholder,
                        'minLength' => (int) ($chromeSearch['min_query_length'] ?? 2),
                        'scopeDefault' => (string) ($chromeSearch['scope_default'] ?? 'all'),
                        'submitLabel' => $labels['search_submit'],
                    ])
                </div>
            @endif

            @if(!empty($localeSwitcherLinks) && (($chromeHeader['show_locale_switcher'] ?? true) === true))
                <div class="locale-switcher" aria-label="{{ $labels['switch_language'] }}">
                    @foreach($localeSwitcherLinks as $switcherLink)
                        <a class="locale-chip {{ !empty($switcherLink['is_active']) ? 'is-active' : '' }}" href="{{ $switcherLink['href'] }}">
                            {{ strtoupper($switcherLink['code']) }}
                        </a>
                    @endforeach
                </div>
            @endif

            {!! $publicChrome['header_actions'] ?? '' !!}
        </div>
    </header>
@endif
