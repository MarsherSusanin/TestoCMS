<form method="POST" action="#" class="grid" style="gap:12px;" data-action-modal-form>
    @csrf
    <input type="hidden" name="modal_id" value="{{ $modalId }}">
    <div class="field" style="margin:0;">
        <label>Объект</label>
        <div class="muted" data-action-text="entity">—</div>
    </div>
    <div class="field" style="margin:0;">
        <label for="{{ $idPrefix }}-locale">Locale</label>
        <select id="{{ $idPrefix }}-locale" name="locale" data-action-field="locale" required>
            @foreach($locales as $locale)
                <option value="{{ $locale }}" @selected($locale === $defaultLocale)>{{ strtoupper($locale) }}</option>
            @endforeach
        </select>
    </div>
    @if(!empty($previewLink))
        <div class="flash success" style="margin:0;">
            Ссылка предпросмотра:
            <a href="{{ $previewLink }}" target="_blank" rel="noreferrer" class="mono">{{ $previewLink }}</a>
        </div>
    @endif
    <div class="inline" style="justify-content:flex-end;">
        <button type="submit" class="btn btn-primary">Сгенерировать ссылку</button>
    </div>
</form>
