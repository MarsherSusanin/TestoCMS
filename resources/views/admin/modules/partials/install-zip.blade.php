<p class="muted panel-section-description">
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
    <div style="margin-top:12px;">
        <button type="submit" class="btn btn-primary">Установить модуль</button>
    </div>
</form>
