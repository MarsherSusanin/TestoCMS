<details class="panel module-card" @if($module->enabled) open @endif>
    <summary class="module-summary">
        <div>
            <strong>{{ $module->name }}</strong>
            <span class="module-path mono">{{ $module->moduleKey }}</span>
        </div>
        <div class="module-meta">
            <span class="status-pill">{{ $module->enabled ? 'active' : 'inactive' }}</span>
            <span>v{{ $module->version }}</span>
            <span>{{ $module->author ?: 'Неизвестный автор' }}</span>
        </div>
    </summary>

    <div class="grid cols-2" style="margin-top:12px;">
        <div>
            <table class="module-info-table">
                <tr>
                    <td class="muted">Статус</td>
                    <td><span class="mono">{{ $module->status }}</span></td>
                </tr>
                <tr>
                    <td class="muted">Provider</td>
                    <td class="mono">{{ $module->provider }}</td>
                </tr>
                <tr>
                    <td class="muted">Путь</td>
                    <td class="mono">{{ $module->installPath }}</td>
                </tr>
                <tr>
                    <td class="muted">Описание</td>
                    <td>{{ $module->description ?: '—' }}</td>
                </tr>
                @if(!empty($module->metadata['docs_url']))
                    <tr>
                        <td class="muted">Docs URL</td>
                        <td><a href="{{ $module->metadata['docs_url'] }}" target="_blank" rel="noreferrer">{{ $module->metadata['docs_url'] }}</a></td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="module-action-stack">
            <div class="module-action-row">
                @if($module->enabled)
                    <form method="POST" action="{{ route('admin.modules.deactivate', $module->id) }}">
                        @csrf
                        <button type="submit" class="btn">Деактивировать</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.modules.activate', $module->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Активировать</button>
                    </form>
                @endif

                @php
                    $moduleActions = [
                        [
                            'type' => 'link',
                            'label' => 'Обновить через ZIP',
                            'href' => '#module_zip_update_'.$module->id,
                        ],
                    ];
                    if (!empty($module->metadata['docs_url'])) {
                        $moduleActions[] = [
                            'type' => 'link',
                            'label' => 'Документация',
                            'href' => $module->metadata['docs_url'],
                            'target' => '_blank',
                            'rel' => 'noreferrer',
                        ];
                    }
                    $moduleActions[] = [
                        'type' => 'form',
                        'label' => 'Удалить модуль',
                        'action' => route('admin.modules.destroy', $module->id),
                        'method' => 'DELETE',
                        'fields' => ['preserve_data' => 0],
                        'confirm' => 'Удалить модуль '.$module->moduleKey.'?',
                        'danger' => true,
                    ];
                @endphp
                @include('admin.partials.row-action-menu', [
                    'items' => $moduleActions,
                    'menuLabel' => 'Действия модуля',
                ])
            </div>

            <form method="POST" action="{{ route('admin.modules.update', $module->id) }}" enctype="multipart/form-data">
                @csrf
                @php
                    $uploadFieldId = 'module_zip_update_'.$module->id;
                @endphp
                <div class="field">
                    <label for="{{ $uploadFieldId }}">Обновить через ZIP</label>
                    <input id="{{ $uploadFieldId }}" type="file" name="module_zip" accept=".zip" required>
                    <small>ZIP должен содержать тот же <span class="mono">id</span> и более высокую версию.</small>
                </div>
                <button type="submit" class="btn">Обновить модуль</button>
            </form>

            <form method="POST" action="{{ route('admin.modules.destroy', $module->id) }}" data-confirm="Удалить модуль {{ $module->moduleKey }}?">
                @csrf
                @method('DELETE')
                <label class="checkbox" style="margin-bottom:10px;">
                    <input type="checkbox" name="preserve_data" value="1">
                    <span>Сохранить данные модуля (если поддерживается)</span>
                </label>
                <button type="submit" class="btn btn-danger">Удалить модуль с сохранением настроек</button>
            </form>
        </div>
    </div>

    @if($module->lastError)
        <div class="module-error">
            <strong>Последняя ошибка:</strong>
            <div class="mono" style="margin-top:6px;">{{ $module->lastError }}</div>
        </div>
    @endif
</details>
