<div class="list-toolbar-head">
    <form method="GET" class="inline" style="align-items:flex-end; gap:10px;">
        @foreach(($hidden ?? []) as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <div class="field" style="margin:0; max-width:220px;">
            <label for="{{ $perPageId }}">{{ $label ?? 'Строк на странице' }}</label>
            <select id="{{ $perPageId }}" name="per_page" onchange="this.form.submit()">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}" @selected((int) $perPage === (int) $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button class="btn btn-small" type="submit">Применить</button></noscript>
    </form>
    <div class="list-summary">{{ $summary }}</div>
</div>
