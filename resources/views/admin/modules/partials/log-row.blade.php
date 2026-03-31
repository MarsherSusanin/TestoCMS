<tr>
    <td class="mono">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
    <td class="mono">{{ $log->module_key }}</td>
    <td class="mono">{{ $log->action }}</td>
    <td><span class="status-pill">{{ $log->status }}</span></td>
    <td>
        @include('admin.partials.json-context-details', ['context' => $log->context, 'summary' => 'показать'])
    </td>
</tr>
