@php
    $jsonContextValue = $context ?? null;
    $jsonContextSummary = (string) ($summary ?? 'показать');
    $jsonContextEmptyLabel = (string) ($emptyLabel ?? '—');
@endphp

@if(!empty($jsonContextValue))
    <details class="json-context-details">
        <summary>{{ $jsonContextSummary }}</summary>
        <pre class="mono">{{ json_encode($jsonContextValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
@else
    <span class="muted">{{ $jsonContextEmptyLabel }}</span>
@endif
