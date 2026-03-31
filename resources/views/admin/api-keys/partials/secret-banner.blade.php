@if(is_array($createdToken) && !empty($createdToken['plain_token']))
    <div class="api-key-secret">
        <strong>Ключ создан. Скопируйте и сохраните сейчас: после обновления страницы он больше не будет показан.</strong>
        <div class="muted api-key-secret-meta">
            {{ $createdToken['name'] ?? '' }} · {{ $createdToken['owner'] ?? '' }}
        </div>
        <code class="mono" data-api-key-secret>{{ $createdToken['plain_token'] }}</code>
        <div class="actions api-key-secret-actions">
            <button type="button" class="btn btn-small" data-api-key-copy>Скопировать ключ</button>
        </div>
    </div>
@endif
