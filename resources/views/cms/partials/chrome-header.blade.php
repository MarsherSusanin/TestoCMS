@if($headerEnabled)
    @php
        $logoSrc = trim((string) ($headerLogo['src'] ?? ''));
        $logoAlt = trim((string) ($headerLogo['alt'] ?? '')) ?: config('app.name');
    @endphp
    <header class="{{ $headerClass }}">
        @if($headerVariant === 'center_logo')
            <div class="topbar-right topbar-right--nav" data-side="left">
                @include('cms.partials.chrome-nav', ['items' => $headerLeftNav, 'navLabel' => 'Primary left'])
            </div>
        @endif

        <a class="brand" href="{{ url('/'.$currentLocale) }}">
            @if($logoSrc !== '')
                <span class="brand-mark brand-mark-image">
                    <img src="{{ $logoSrc }}" alt="{{ $logoAlt }}" loading="lazy">
                </span>
            @else
                <span class="brand-mark" aria-hidden="true"></span>
            @endif
            <span class="brand-copy">
                <p class="brand-title">{{ config('app.name') }}</p>
                @if(($chromeHeader['show_brand_subtitle'] ?? true) === true)
                    <p class="brand-subtitle">{{ $footerTagline }}</p>
                @endif
            </span>
        </a>

        <div class="topbar-right topbar-right--primary" data-side="right">
            @if($headerVariant !== 'center_logo')
                <div class="topbar-nav-shell topbar-nav-shell--{{ $headerMenuPosition }}">
                    @include('cms.partials.chrome-nav', ['items' => $headerNavLinks, 'navLabel' => 'Primary'])
                </div>
            @elseif(!empty($headerRightNav))
                <div class="topbar-nav-shell topbar-nav-shell--right">
                    @include('cms.partials.chrome-nav', ['items' => $headerRightNav, 'navLabel' => 'Primary right'])
                </div>
            @endif

            <div class="topbar-tools">
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
        </div>
    </header>
@endif
