@php
    $managementStatsItems = is_iterable($items ?? null) ? $items : [];
    $managementStatsClass = trim((string) ($class ?? ''));
@endphp

<div class="cards management-stats {{ $managementStatsClass }}">
    @foreach($managementStatsItems as $item)
        <div class="card">
            <div class="label">{{ $item['label'] ?? '' }}</div>
            <div class="value {{ $item['value_class'] ?? '' }}">{{ $item['value'] ?? '—' }}</div>
            @if(!empty($item['hint']))
                <div class="muted" style="margin-top:6px;">{{ $item['hint'] }}</div>
            @endif
        </div>
    @endforeach
</div>
