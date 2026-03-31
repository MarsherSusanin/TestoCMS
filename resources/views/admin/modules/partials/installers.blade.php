<div class="grid cols-2">
    <section class="panel">
        <h2 class="panel-heading">Установка из ZIP</h2>
        <p class="muted module-install-note">
            Максимальный размер: {{ (int) ($modulesConfig['max_zip_size_mb'] ?? 30) }} MB.
        </p>
        <form method="POST" action="{{ route('admin.modules.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="module_zip_install">ZIP архив модуля</label>
                <input id="module_zip_install" type="file" name="module_zip" accept=".zip" required>
            </div>
            <label class="checkbox">
                <input type="checkbox" name="activate_now" value="1">
                <span>Активировать сразу после установки</span>
            </label>
            <div class="module-action-top">
                <button type="submit" class="btn btn-primary">Установить модуль</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-heading">Установка из локального пути</h2>
        <p class="muted module-install-note">
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
                <label class="checkbox module-symlink-toggle">
                    <input type="checkbox" name="use_symlink" value="1">
                    <span>Использовать symlink (dev mode)</span>
                </label>
            @endif
            <div class="module-action-top">
                <button type="submit" class="btn">Установить локальный модуль</button>
            </div>
        </form>
    </section>
</div>
