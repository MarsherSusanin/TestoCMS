@php
    $primary = $primary ?? null;
    $items = array_values(array_filter((array) ($items ?? []), static fn ($item) => is_array($item) && !empty($item['label'])));
    $align = $align ?? 'end';
    $menuLabel = $menuLabel ?? 'Дополнительные действия';
    $renderAttrs = static function (array $attrs = []): string {
        $html = [];
        foreach ($attrs as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            if ($value === true) {
                $html[] = e((string) $name);
                continue;
            }
            $html[] = e((string) $name).'="'.e((string) $value).'"';
        }

        return implode(' ', $html);
    };
@endphp

<div class="action-row {{ $class ?? '' }}">
    @if(is_array($primary) && !empty($primary['label']))
        @if(($primary['type'] ?? 'link') === 'link')
            <a
                class="btn btn-small action-row-primary {{ $primary['class'] ?? '' }}"
                href="{{ $primary['href'] ?? '#' }}"
                @if(!empty($primary['target'])) target="{{ $primary['target'] }}" @endif
                @if(!empty($primary['rel'])) rel="{{ $primary['rel'] }}" @endif
                {!! $renderAttrs((array) ($primary['attrs'] ?? [])) !!}
            >{{ $primary['label'] }}</a>
        @elseif(($primary['type'] ?? '') === 'button')
            <button
                type="{{ $primary['button_type'] ?? 'button' }}"
                class="btn btn-small action-row-primary {{ $primary['class'] ?? '' }}"
                {!! $renderAttrs((array) ($primary['attrs'] ?? [])) !!}
                {!! $primary['extra'] ?? '' !!}
            >{{ $primary['label'] }}</button>
        @endif
    @endif

    @if($items !== [])
        <div class="action-menu" data-action-menu data-action-align="{{ $align }}">
            <button
                type="button"
                class="btn btn-small btn-ghost action-menu-trigger"
                data-action-trigger
                aria-expanded="false"
                aria-label="{{ $menuLabel }}"
                title="{{ $menuLabel }}"
            >⋯</button>
            <div class="action-menu-panel" data-action-panel hidden>
                @foreach($items as $item)
                    @php
                        $itemType = $item['type'] ?? 'link';
                        $itemClass = trim('action-menu-item '.($item['class'] ?? '').(!empty($item['danger']) ? ' is-danger' : ''));
                    @endphp
                    @if($itemType === 'link')
                        <a
                            class="{{ $itemClass }}"
                            href="{{ $item['href'] ?? '#' }}"
                            @if(!empty($item['target'])) target="{{ $item['target'] }}" @endif
                            @if(!empty($item['rel'])) rel="{{ $item['rel'] }}" @endif
                            {!! $renderAttrs((array) ($item['attrs'] ?? [])) !!}
                            @if(!empty($item['extra'])) {!! $item['extra'] !!} @endif
                        >{{ $item['label'] }}</a>
                    @elseif($itemType === 'button')
                        <button
                            type="{{ $item['button_type'] ?? 'button' }}"
                            class="{{ $itemClass }}"
                            {!! $renderAttrs((array) ($item['attrs'] ?? [])) !!}
                            @if(!empty($item['extra'])) {!! $item['extra'] !!} @endif
                        >{{ $item['label'] }}</button>
                    @elseif($itemType === 'form')
                        <button
                            type="button"
                            class="{{ $itemClass }}"
                            data-action-submit="{{ $item['action'] ?? '#' }}"
                            data-action-method="{{ strtoupper((string) ($item['method'] ?? 'POST')) }}"
                            @if(!empty($item['confirm'])) data-confirm="{{ $item['confirm'] }}" @endif
                            @if(!empty($item['fields'])) data-action-fields="{{ e(json_encode((array) $item['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}" @endif
                            {!! $renderAttrs((array) ($item['attrs'] ?? [])) !!}
                            @if(!empty($item['extra'])) {!! $item['extra'] !!} @endif
                        >{{ $item['label'] }}</button>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
