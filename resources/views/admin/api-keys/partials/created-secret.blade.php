<div class="api-key-secret">
    <strong>Ключ создан. Скопируйте и сохраните сейчас: после обновления страницы он больше не будет показан.</strong>
    <div style="margin-top:8px;" class="muted">
        {{ $createdToken['name'] ?? '' }} · {{ $createdToken['owner'] ?? '' }}
    </div>
    <code class="mono" data-api-key-secret>{{ $createdToken['plain_token'] }}</code>
    <div class="actions" style="margin-top:10px;">
        <button type="button" class="btn btn-small" data-api-key-copy>Скопировать ключ</button>
    </div>
</div>
