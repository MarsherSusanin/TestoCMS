@extends('admin.layout')

@section('title', 'Аудит')

@section('content')
    <div class="page-header">
        <div>
            <h1>Журнал аудита</h1>
            <p>Последние критичные действия, зафиксированные CMS.</p>
        </div>
    </div>

    <section class="panel">
        <form method="GET" action="{{ route('admin.audit.index') }}" class="inline" style="margin-bottom:12px;">
            <div class="field" style="flex:1; min-width:240px; margin:0;">
                <label for="audit-action" style="margin-bottom:4px;">Фильтр по действию</label>
                <input id="audit-action" type="text" name="action" value="{{ $actionFilter }}" placeholder="post.publish, page.update, asset.create...">
            </div>
            <div class="actions" style="align-self:flex-end;">
                <button class="btn" type="submit">Фильтр</button>
                @if($actionFilter !== '')
                    <a href="{{ route('admin.audit.index') }}" class="btn">Сбросить</a>
                @endif
            </div>
        </form>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Время</th>
                <th>Действие</th>
                <th>Пользователь</th>
                <th>Сущность</th>
                <th>Контекст</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="mono">#{{ $log->id }}</td>
                    <td>{{ optional($log->created_at)->toDayDateTimeString() }}</td>
                    <td class="mono">{{ $log->action }}</td>
                    <td>{{ $log->actor?->email ?? 'system' }}</td>
                    <td>
                        @if($log->entity_type)
                            <div class="mono" style="font-size:12px;">{{ class_basename($log->entity_type) }}</div>
                            <div>#{{ $log->entity_id }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if(!empty($log->context))
                            <details>
                                <summary>показать</summary>
                                <pre class="mono" style="font-size:12px; white-space:pre-wrap; margin:8px 0 0;">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Записей аудита пока нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="pagination">{{ $logs->links() }}</div>
    </section>
@endsection
