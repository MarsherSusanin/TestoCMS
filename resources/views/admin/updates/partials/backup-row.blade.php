<tr>
    <td class="mono">{{ $backup->backup_key }}</td>
    <td class="mono">{{ $backup->from_version ?: '—' }} → {{ $backup->to_version ?: '—' }}</td>
    <td>
        <span class="status-pill">{{ $backup->status }}</span>
        @if($backup->restore_status)
            <span class="status-pill">restore: {{ $backup->restore_status }}</span>
        @endif
    </td>
    <td class="mono">{{ optional($backup->created_at)->format('Y-m-d H:i:s') }}</td>
    <td>
        @include('admin.partials.row-action-menu', [
            'items' => [[
                'type' => 'form',
                'label' => 'Rollback',
                'action' => route('admin.updates.rollback', $backup),
                'confirm' => 'Выполнить rollback из backup '.$backup->backup_key.'?',
                'danger' => true,
            ]],
            'menuLabel' => 'Действия с backup',
        ])
    </td>
</tr>
