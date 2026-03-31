@if(($chromeFooter['enabled'] ?? true) === true)
    <footer class="{{ $footerClass }}">
        <div class="footer-block">
            @if(($chromeFooter['show_brand'] ?? true) === true)
                <div class="footer-meta">
                    <strong>{{ config('app.name') }}</strong>
                    @if(($chromeFooter['show_tagline'] ?? true) === true)
                        <span>· {{ $footerTagline }}</span>
                    @endif
                </div>
            @elseif(($chromeFooter['show_tagline'] ?? true) === true)
                <div class="footer-meta"><span>{{ $footerTagline }}</span></div>
            @endif

            @if(!empty($footerLegalLinks))
                <div class="footer-legal-row" style="margin-top:8px;">
                    @foreach($footerLegalLinks as $link)
                        <a href="{{ $link['href'] }}"
                           @if(!empty($link['target_blank'])) target="_blank" @endif
                           @if(!empty($link['rel'])) rel="{{ $link['rel'] }}" @endif>{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="footer-block">
            @if(!empty($footerLinks))
                <div class="footer-links">
                    @foreach($footerLinks as $link)
                        <a href="{{ $link['href'] }}"
                           @if(!empty($link['target_blank'])) target="_blank" @endif
                           @if(!empty($link['rel'])) rel="{{ $link['rel'] }}" @endif>{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif

            @if(!empty($footerSocialLinks))
                <div class="footer-links" style="margin-top:8px;">
                    @foreach($footerSocialLinks as $link)
                        <a href="{{ $link['href'] }}"
                           @if(!empty($link['target_blank'])) target="_blank" @endif
                           @if(!empty($link['rel'])) rel="{{ $link['rel'] }}" @endif>{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        @if($showFooterSearch)
            <div class="footer-block footer-search-wrap">
                @include('cms.partials.search-shell', [
                    'action' => url('/'.trim($currentLocale.'/'.$searchPathSlug, '/')),
                    'value' => '',
                    'placeholder' => $searchPlaceholder,
                    'minLength' => (int) ($chromeSearch['min_query_length'] ?? 2),
                    'scopeDefault' => (string) ($chromeSearch['scope_default'] ?? 'all'),
                    'submitLabel' => $labels['search_submit'],
                ])
            </div>
        @endif
    </footer>
@endif
