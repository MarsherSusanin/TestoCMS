@php
    $icon = $icon ?? '—';
    $title = $title ?? 'Пока пусто';
    $description = $description ?? '';
    $hints = $hints ?? [];
    $actions = $actions ?? [];
    $footer = $footer ?? null;
@endphp

@once
    @push('head')
        <style>
            .admin-empty-state {
                display:grid;
                grid-template-columns: 60px minmax(0, 1fr);
                gap:18px;
                align-items:flex-start;
                padding:22px;
                border:1px dashed #cfd8e3;
                border-radius:16px;
                background: linear-gradient(180deg, #fbfdff 0%, #f6f9ff 100%);
            }
            .admin-empty-state__icon {
                width:60px;
                height:60px;
                border-radius:18px;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:22px;
                font-weight:800;
                color:#0f172a;
                background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                border:1px solid #bfdbfe;
                box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
            }
            .admin-empty-state__title {
                margin:0 0 8px;
                font-size:24px;
                letter-spacing:-.02em;
            }
            .admin-empty-state__description {
                margin:0;
                max-width:760px;
                color:var(--muted);
                line-height:1.55;
            }
            .admin-empty-state__hints {
                display:grid;
                gap:10px;
                margin-top:16px;
            }
            .admin-empty-state__hint {
                padding:12px 14px;
                border-radius:12px;
                border:1px solid #dbe4f0;
                background:rgba(255,255,255,.82);
                color:#334155;
                line-height:1.5;
            }
            .admin-empty-state__actions {
                display:flex;
                gap:10px;
                flex-wrap:wrap;
                margin-top:18px;
            }
            .admin-empty-state__footer {
                margin-top:16px;
                color:var(--muted);
                line-height:1.5;
            }
            @media (max-width: 860px) {
                .admin-empty-state {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    @endpush
@endonce

<div class="admin-empty-state">
    <div class="admin-empty-state__icon">{{ $icon }}</div>
    <div class="admin-empty-state__body">
        <h2 class="admin-empty-state__title">{{ $title }}</h2>
        <p class="admin-empty-state__description">{!! $description !!}</p>

        @if($hints !== [])
            <div class="admin-empty-state__hints">
                @foreach($hints as $hint)
                    <div class="admin-empty-state__hint">
                        @if(!empty($hint['title']))
                            <strong>{{ $hint['title'] }}</strong>
                            @if(!empty($hint['body']))
                                {!! ' ' . $hint['body'] !!}
                            @endif
                        @else
                            {!! $hint['body'] ?? '' !!}
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($actions !== [])
            <div class="admin-empty-state__actions">
                @foreach($actions as $action)
                    @php
                        $type = $action['type'] ?? 'link';
                        $classes = trim('btn ' . ($action['class'] ?? ''));
                        $disabled = (bool) ($action['disabled'] ?? false);
                        $extra = $action['extra'] ?? '';
                    @endphp

                    @if($type === 'button')
                        <button type="{{ $action['button_type'] ?? 'button' }}" class="{{ $classes }}" {!! $extra !!} @disabled($disabled)>{{ $action['label'] }}</button>
                    @elseif($disabled)
                        <span class="{{ $classes }}" aria-disabled="true" style="opacity:.55; cursor:not-allowed;">{{ $action['label'] }}</span>
                    @else
                        <a href="{{ $action['href'] ?? '#' }}" class="{{ $classes }}" {!! $extra !!}>{{ $action['label'] }}</a>
                    @endif
                @endforeach
            </div>
        @endif

        @if($footer)
            <div class="admin-empty-state__footer">{!! $footer !!}</div>
        @endif
    </div>
</div>
