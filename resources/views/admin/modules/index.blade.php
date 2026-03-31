@extends('admin.layout')

@section('title', 'Модули')

@push('head')
    @include('admin.modules.partials.styles')
@endpush

@section('content')
    @php
        $activeModules = array_values(array_filter($modules, static fn ($module) => (bool) $module->enabled));
        $inactiveModules = array_values(array_filter($modules, static fn ($module) => ! (bool) $module->enabled));
    @endphp

    @include('admin.partials.action-toolbar', [
        'title' => 'Модули',
        'description' => 'Установка, активация и обновление доверенных PHP-модулей (WordPress-like).',
        'primaryAction' => [
            'type' => 'link',
            'label' => 'Документация по модулям',
            'href' => route('admin.modules.docs'),
        ],
    ])

    <div class="grid cols-2">
        @include('admin.partials.settings-panel', [
            'title' => 'Установка из ZIP',
            'bodyView' => 'admin.modules.partials.install-zip',
            'bodyData' => ['modulesConfig' => $modulesConfig],
        ])

        @include('admin.partials.settings-panel', [
            'title' => 'Установка из локального пути',
            'bodyView' => 'admin.modules.partials.install-local',
            'bodyData' => ['modulesConfig' => $modulesConfig],
        ])
    </div>

    <section class="panel">
        <div class="inline" style="justify-content: space-between; margin-bottom:10px;">
            <h2 class="panel-section-title" style="margin:0;">Активные модули</h2>
            <span class="status-pill">{{ count($activeModules) }}</span>
        </div>

        @if($activeModules === [])
            <p class="muted">Активных модулей пока нет.</p>
        @else
            @foreach($activeModules as $module)
                @include('admin.modules.partials.module-card', ['module' => $module])
            @endforeach
        @endif
    </section>

    <section class="panel">
        <div class="inline" style="justify-content: space-between; margin-bottom:10px;">
            <h2 class="panel-section-title" style="margin:0;">Установленные, но неактивные</h2>
            <span class="status-pill">{{ count($inactiveModules) }}</span>
        </div>

        @if($inactiveModules === [])
            <p class="muted">Неактивных установленных модулей нет.</p>
        @else
            @foreach($inactiveModules as $module)
                @include('admin.modules.partials.module-card', ['module' => $module])
            @endforeach
        @endif
    </section>

    <section class="panel">
        <div class="inline" style="justify-content: space-between; margin-bottom:10px;">
            <h2 class="panel-section-title" style="margin:0;">Bundled модули</h2>
            <span class="status-pill">{{ count($bundledModules ?? []) }}</span>
        </div>

        @if(empty($bundledModules))
            <p class="muted">Bundled пакеты не найдены.</p>
        @else
            @foreach($bundledModules as $bundledModule)
                @include('admin.modules.partials.bundled-card', ['module' => $bundledModule])
            @endforeach
        @endif
    </section>

    <section class="panel">
        <h2 class="panel-section-title">Логи установки и управления</h2>
        @include('admin.partials.operation-log-table', [
            'headers' => ['Время', 'Модуль', 'Действие', 'Статус', 'Контекст'],
            'logs' => $logs,
            'emptyMessage' => 'Логи пока пусты.',
            'rowView' => 'admin.modules.partials.log-row',
        ])
    </section>

    @include('admin.partials.settings-panel', [
        'title' => 'Для разработчиков',
        'description' => 'Документация по структуре модулей, lifecycle и примерам provider/routes/views.',
        'bodyView' => 'admin.modules.docs-link',
    ])
@endsection
