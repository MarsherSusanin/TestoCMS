<form method="POST" action="#" class="grid" style="gap:12px;" data-action-modal-form>
    @csrf
    @method('PUT')
    <div class="field" style="margin:0;">
        <label for="{{ $idPrefix }}-name">Название</label>
        <input id="{{ $idPrefix }}-name" type="text" name="name" maxlength="190" data-action-field="name" required>
    </div>
    <div class="field" style="margin:0;">
        <label for="{{ $idPrefix }}-description">Описание</label>
        <textarea id="{{ $idPrefix }}-description" name="description" rows="4" maxlength="2000" data-action-field="description"></textarea>
    </div>
    <div class="inline" style="justify-content:flex-end;">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>
