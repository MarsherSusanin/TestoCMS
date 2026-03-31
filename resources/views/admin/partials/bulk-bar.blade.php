<div class="bulk-controls" data-bulk-shell hidden>
    <span class="muted" data-bulk-count>{{ $countLabel ?? 'Выбрано: 0' }}</span>
    <select name="action" required data-bulk-action>
        <option value="">{{ $placeholder ?? 'Массовое действие…' }}</option>
        @foreach($actions as $action)
            <option value="{{ $action['value'] }}">{{ $action['label'] }}</option>
        @endforeach
    </select>
    <button class="btn btn-small" type="submit" data-bulk-submit disabled>{{ $submitLabel ?? 'Применить' }}</button>
</div>
