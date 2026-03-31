@extends('admin.layout')

@section('title', 'Обновления')

@php
    $settings = is_array($snapshot['settings'] ?? null) ? $snapshot['settings'] : [];
    $state = is_array($snapshot['state'] ?? null) ? $snapshot['state'] : [];
    $available = is_array($state['available_release'] ?? null) ? $state['available_release'] : null;
    $pending = is_array($state['pending_package'] ?? null) ? $state['pending_package'] : null;
    $currentVersion = (string) ($snapshot['current_version'] ?? '1.0.0');
    $availableVersion = trim((string) ($snapshot['available_version'] ?? ''));
    $executionMode = (string) ($snapshot['execution_mode'] ?? 'auto');
    $stats = [
        ['label' => 'Текущая версия', 'value' => $currentVersion, 'value_class' => 'mono updates-value-lg'],
        ['label' => 'Доступная версия', 'value' => $availableVersion !== '' ? $availableVersion : '—', 'value_class' => 'mono updates-value-lg'],
        ['label' => 'Режим выполнения', 'value' => $executionMode, 'value_class' => 'mono updates-value-md'],
        ['label' => 'Последняя проверка', 'value' => !empty($state['last_check_at']) ? $state['last_check_at'] : '—', 'value_class' => 'mono updates-value-sm'],
    ];
@endphp

@push('head')
    @include('admin.updates.partials.styles')
@endpush

@section('content')
    @include('admin.partials.action-toolbar', [
        'title' => 'Обновления',
        'description' => 'Update Center для ядра TestoCMS: облачный one-click и ручной ZIP-пакет.',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Журнал операций',
            'href' => route('admin.updates.logs'),
        ],
    ])

    @include('admin.partials.management-stats', ['items' => $stats])

    <div class="grid cols-2">
        @include('admin.partials.settings-panel', [
            'title' => 'Настройки канала обновлений',
            'bodyView' => 'admin.updates.partials.settings-form',
            'bodyData' => ['settings' => $settings],
        ])

        @include('admin.partials.settings-panel', [
            'title' => 'Проверка и применение',
            'bodyView' => 'admin.updates.partials.check-and-apply',
            'bodyData' => ['available' => $available, 'pending' => $pending],
        ])
    </div>

    <section class="panel">
        <h2 class="panel-section-title">Backups и rollback</h2>
        @include('admin.partials.operation-log-table', [
            'headers' => ['Backup key', 'Версии', 'Статус', 'Создан', 'Действия'],
            'logs' => $backups,
            'emptyMessage' => 'Бэкапы обновлений ещё не создавались.',
            'rowView' => 'admin.updates.partials.backup-row',
        ])
    </section>

    <section class="panel">
        <h2 class="panel-section-title">Последние операции</h2>
        @include('admin.partials.operation-log-table', [
            'headers' => ['Время', 'Действие', 'Статус', 'Версии', 'Сообщение', 'Контекст'],
            'logs' => $logs,
            'emptyMessage' => 'Логи обновлений пока пусты.',
            'rowView' => 'admin.updates.partials.log-row',
            'rowData' => ['mode' => 'full'],
        ])
    </section>
@endsection
