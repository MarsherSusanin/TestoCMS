<p class="muted panel-section-description">
    Разрешённые root-директории:
    <span class="mono">{{ implode(', ', (array) ($modulesConfig['local_install_roots'] ?? [])) }}</span>
</p>
<form method="POST" action="{{ route('admin.modules.install-local') }}">
    @csrf
    <div class="field">
        <label for="module_local_path">Путь к папке модуля</label>
        <input id="module_local_path" type="text" name="local_path" placeholder="/abs/path/to/module" required>
    </div>
    <label class="checkbox">
        <input type="checkbox" name="activate_now" value="1">
        <span>Активировать сразу после установки</span>
    </label>
    @if(!empty($modulesConfig['allow_symlink_dev']))
        <label class="checkbox" style="margin-top:6px;">
            <input type="checkbox" name="use_symlink" value="1">
            <span>Использовать symlink (dev mode)</span>
        </label>
    @endif
    <div style="margin-top:12px;">
        <button type="submit" class="btn">Установить локальный модуль</button>
    </div>
</form>
