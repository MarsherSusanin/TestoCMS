<div class="updates-actions-inline">
    <form method="POST" action="{{ route('admin.updates.check') }}">
        @csrf
        <button type="submit" class="btn">Проверить обновления</button>
    </form>
    <form method="POST" action="{{ route('admin.updates.apply') }}" data-confirm="Запустить применение обновления ядра? Будет создан backup и выполнены миграции.">
        @csrf
        <button type="submit" class="btn btn-primary">Применить обновление</button>
    </form>
</div>

@if($available)
    <div class="panel updates-info-card">
        <strong>Доступен релиз:</strong>
        <span class="mono">v{{ $available['version'] ?? 'unknown' }}</span>
        <div class="muted">Канал: <span class="mono">{{ $available['channel'] ?? 'n/a' }}</span></div>
        @if(!empty($available['changelog_url']))
            <div class="muted"><a href="{{ $available['changelog_url'] }}" target="_blank" rel="noreferrer">Список изменений</a></div>
        @endif
    </div>
@endif

@if($pending)
    <div class="panel updates-info-card">
        <strong>Pending package:</strong>
        <span class="mono">v{{ $pending['version'] ?? 'unknown' }}</span>
        <div class="muted">Источник: <span class="mono">{{ $pending['source'] ?? 'unknown' }}</span></div>
        @if(!empty($pending['zip_path']))
            <div class="muted">Файл: <span class="mono">{{ $pending['zip_path'] }}</span></div>
        @endif
    </div>
@endif

<hr class="updates-separator">

<h3 class="panel-section-title">Ручной пакет (ZIP)</h3>
<form method="POST" action="{{ route('admin.updates.upload') }}" enctype="multipart/form-data">
    @csrf
    <div class="field">
        <label for="release-zip">Updater ZIP ядра</label>
        <input id="release-zip" type="file" name="release_zip" accept=".zip" required>
        <small>Загружайте пакет вида <span class="mono">testocms-vX.Y.Z-updater.zip</span> с <span class="mono">release.json</span> и артефактом <span class="mono">core-updater</span>. Shared-hosting ZIP нужен только для ручного деплоя.</small>
    </div>
    <div class="field">
        <label for="release-sig">Подпись релиза</label>
        <input id="release-sig" type="file" name="release_sig" accept=".sig,.txt">
        <textarea id="release-signature" name="release_signature" rows="2" class="mono" placeholder="…или вставьте base64-подпись (.sig)"></textarea>
        <small>Обязательно. Detached Ed25519-подпись ZIP, выданная издателем релиза. Пакет без действительной подписи к ключу из настроек отклоняется.</small>
    </div>
    <button type="submit" class="btn">Загрузить пакет</button>
</form>
