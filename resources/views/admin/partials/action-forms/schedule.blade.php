<form method="POST" action="#" class="grid" style="gap:12px;" data-action-modal-form>
    @csrf
    <div class="field" style="margin:0;">
        <label>Объект</label>
        <div class="muted" data-action-text="entity">—</div>
    </div>
    <div class="field" style="margin:0;">
        <label for="{{ $idPrefix }}-action">Действие</label>
        <select id="{{ $idPrefix }}-action" name="action" data-action-field="action" required>
            <option value="publish">Опубликовать</option>
            <option value="unpublish">Снять с публикации</option>
        </select>
    </div>
    <div class="field" style="margin:0;">
        <label for="{{ $idPrefix }}-due-at">Когда выполнить</label>
        <input id="{{ $idPrefix }}-due-at" type="datetime-local" name="due_at" data-action-field="due_at" required>
    </div>
    <div class="inline" style="justify-content:flex-end;">
        <button type="submit" class="btn btn-primary">Создать schedule</button>
    </div>
</form>
