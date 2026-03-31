<details class="panel module-card" @if(empty($module['installed']) || !empty($module['recoverable'])) open @endif>
    <summary class="module-summary">
        <div>
            <strong>{{ $module['name'] ?? 'Bundled module' }}</strong>
            <span class="module-path mono">{{ $module['module_key'] ?? '—' }}</span>
        </div>
        <div class="module-meta">
            <span class="status-pill">
                {{ !empty($module['installed']) ? (!empty($module['enabled']) ? 'active' : 'installed') : (!empty($module['recoverable']) ? 'recoverable' : 'bundled') }}
            </span>
            <span>v{{ $module['version'] ?? '—' }}</span>
            <span>{{ $module['author'] ?: 'Неизвестный автор' }}</span>
        </div>
    </summary>

    <div class="grid cols-2" style="margin-top:12px;">
        <div>
            <table class="module-info-table">
                <tr>
                    <td class="muted">Поставка</td>
                    <td><span class="mono">bundled package</span></td>
                </tr>
                <tr>
                    <td class="muted">Provider</td>
                    <td class="mono">{{ $module['provider'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="muted">Исходный путь</td>
                    <td class="mono">{{ $module['source_path'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="muted">Описание</td>
                    <td>{{ $module['description'] ?: '—' }}</td>
                </tr>
            </table>
        </div>

        <div class="module-action-stack">
            @if(!empty($module['installed']))
                <div class="field">
                    <label>Статус</label>
                    <div class="muted">Модуль уже установлен. Управление доступно в списке установленных модулей ниже.</div>
                </div>
            @else
                <div class="module-action-row">
                    <form method="POST" action="{{ route('admin.modules.install-bundled', ['moduleKey' => $module['route_key']]) }}">
                        @csrf
                        @if(!empty($module['recoverable']))
                            <div class="muted" style="margin-bottom:10px;">
                                Найдена существующая папка установки. Модуль будет восстановлен без перезаписи файлов:
                                <span class="mono">{{ $module['recoverable_install_path'] ?? '—' }}</span>
                            </div>
                        @endif
                        <label class="checkbox" style="margin-bottom:10px;">
                            <input type="checkbox" name="activate_now" value="1">
                            <span>Активировать сразу после установки</span>
                        </label>
                        <button type="submit" class="btn btn-primary">
                            {{ !empty($module['recoverable']) ? 'Восстановить модуль' : 'Установить bundled-модуль' }}
                        </button>
                    </form>
                    @php
                        $bundledActions = [];
                        if (!empty($module['metadata']['docs_url'])) {
                            $bundledActions[] = [
                                'type' => 'link',
                                'label' => 'Документация',
                                'href' => $module['metadata']['docs_url'],
                                'target' => '_blank',
                                'rel' => 'noreferrer',
                            ];
                        }
                    @endphp
                    @if($bundledActions !== [])
                        @include('admin.partials.row-action-menu', [
                            'items' => $bundledActions,
                            'menuLabel' => 'Действия bundled модуля',
                        ])
                    @endif
                </div>
            @endif
        </div>
    </div>
</details>
