<tr>
    <td class="mono">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
    <td class="mono">{{ $log->action }}</td>
    <td><span class="status-pill">{{ $log->status }}</span></td>
    @if(($mode ?? 'full') === 'full')
        <td class="mono">{{ $log->from_version ?: '—' }} → {{ $log->to_version ?: '—' }}</td>
        <td>{{ $log->message ?: '—' }}</td>
    @endif
    <td>
        @include('admin.partials.json-context-details', ['context' => $log->context, 'summary' => 'показать'])
    </td>
</tr>
