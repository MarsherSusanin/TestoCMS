<tr>
    <td class="mono">{{ optional($log->attempted_at ?? $log->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
    <td><code>{{ $log->event_name }}</code></td>
    <td class="mono">{{ $log->endpoint?->url ?? '—' }}</td>
    <td>
        <span class="status-pill {{ ($log->status ?? '') === 'failed' ? 'status-pill--draft' : '' }}">
            {{ $log->status }}
        </span>
    </td>
    <td>{{ $log->http_status ?? '—' }}</td>
    <td>
        @include('admin.partials.json-context-details', [
            'context' => $log->payload,
            'summary' => 'payload',
        ])
    </td>
    <td>
        @if(filled($log->response_body))
            <details class="json-context-details">
                <summary>response</summary>
                <pre class="mono">{{ $log->response_body }}</pre>
            </details>
        @else
            <span class="muted">—</span>
        @endif
    </td>
    <td>
        @if(($log->status ?? '') === 'failed' && $log->endpoint)
            <form method="POST" action="{{ route('booking.admin.settings.webhook-deliveries.retry', $log) }}">
                @csrf
                <button class="btn" type="submit">Retry</button>
            </form>
        @else
            <span class="muted">—</span>
        @endif
    </td>
</tr>
