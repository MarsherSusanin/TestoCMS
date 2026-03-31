<form method="POST" action="#" class="grid" style="gap:12px;" data-action-modal-form>
    @csrf
    <div class="field" style="margin:0;">
        <label>Пользователь</label>
        <div class="muted" data-action-text="entity">—</div>
    </div>
    <div class="field" style="margin:0;">
        <label for="{{ $idPrefix }}-password">Новый пароль</label>
        <input id="{{ $idPrefix }}-password" type="password" name="password" required>
    </div>
    <div class="field" style="margin:0;">
        <label for="{{ $idPrefix }}-password-confirmation">Подтвердите пароль</label>
        <input id="{{ $idPrefix }}-password-confirmation" type="password" name="password_confirmation" required>
    </div>
    <div class="inline" style="justify-content:flex-end;">
        <button type="submit" class="btn btn-primary">Обновить пароль</button>
    </div>
</form>
