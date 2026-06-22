@php
    $items = is_array($items ?? null) ? $items : [];
    $navLabel = (string) ($navLabel ?? 'Primary');
@endphp

@if($items !== [])
    <nav class="nav-pills" aria-label="{{ $navLabel }}">
        @foreach($items as $nav)
            @php
                $children = is_array($nav['children'] ?? null) ? $nav['children'] : [];
                $isDisclosure = $children !== [];
                $isActive = !empty($nav['is_active']);
            @endphp
            @if($isDisclosure)
                <div class="nav-disclosure {{ $isActive ? 'is-active' : '' }}" data-cms-nav-disclosure>
                    <button
                        type="button"
                        class="nav-pill nav-pill-disclosure {{ $isActive ? 'is-active' : '' }}"
                        data-cms-nav-disclosure-toggle
                        aria-haspopup="true"
                        aria-expanded="false">
                        <span>{{ $nav['label'] }}</span>
                        <span class="nav-pill-caret" aria-hidden="true">▾</span>
                    </button>
                    <div class="nav-submenu" data-cms-nav-submenu hidden>
                        @foreach($children as $child)
                            <a
                                class="nav-submenu-link {{ !empty($child['is_active']) ? 'is-active' : '' }}"
                                href="{{ $child['href'] }}"
                                @if(!empty($child['target_blank'])) target="_blank" @endif
                                @if(!empty($child['rel'])) rel="{{ $child['rel'] }}" @endif
                                @if(!empty($child['is_active'])) aria-current="page" @endif>{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @else
                <a
                    class="nav-pill {{ $isActive ? 'is-active' : '' }}"
                    href="{{ $nav['href'] }}"
                    @if(!empty($nav['target_blank'])) target="_blank" @endif
                    @if(!empty($nav['rel'])) rel="{{ $nav['rel'] }}" @endif
                    @if($isActive) aria-current="page" @endif>{{ $nav['label'] }}</a>
            @endif
        @endforeach
    </nav>
@endif
