@if($backups === [])
    <p class="muted">Бэкапы обновлений ещё не создавались.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Backup key</th>
                <th>Версии</th>
                <th>Статус</th>
                <th>Создан</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @foreach($backups as $backup)
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
                        <form method="POST" action="{{ route('admin.updates.rollback', $backup) }}" data-confirm="Выполнить rollback из backup {{ $backup->backup_key }}?">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-small">Rollback</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
