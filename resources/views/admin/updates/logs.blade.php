@extends('admin.layout')

@section('title', 'Журнал обновлений')

@section('content')
    <div class="page-header">
        <div>
            <h1>Журнал обновлений</h1>
            <p>История проверок, загрузок, применений и откатов ядра.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('admin.updates.index') }}">Назад к обновлениям</a>
        </div>
    </div>

    <section class="panel">
        <h2 class="panel-section-title">Логи</h2>
        @include('admin.partials.operation-log-table', [
            'headers' => ['Время', 'Действие', 'Статус', 'Версии', 'Сообщение', 'Контекст'],
            'logs' => $logs,
            'emptyMessage' => 'Логи обновлений пока пусты.',
            'rowView' => 'admin.updates.partials.log-row',
            'rowData' => ['mode' => 'full'],
        ])
    </section>

    <section class="panel">
        <h2 class="panel-section-title">Backups</h2>
        @include('admin.partials.operation-log-table', [
            'headers' => ['Backup key', 'Версии', 'Статус', 'Создан', 'Откат'],
            'logs' => $backups,
            'emptyMessage' => 'Бэкапы обновлений ещё не создавались.',
            'rowView' => 'admin.updates.partials.backup-row',
        ])
    </section>
@endsection
