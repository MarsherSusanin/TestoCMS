<form method="POST" action="{{ route('admin.theme.update') }}" class="panel" id="theme-builder-form">
    @csrf
    @method('PUT')

    <h2 style="margin-top:0;">Конструктор темы</h2>
    <p class="muted" style="margin-top:-4px;">Меняйте палитру и шрифты. Превью обновляется сразу.</p>

    <div class="grid cols-3">
        <div class="field">
            <label for="preset_key">Базовый пресет</label>
            <select id="preset_key" name="preset_key" data-theme-field="preset_key">
                @foreach($presets as $presetKey => $preset)
                    <option value="{{ $presetKey }}" @selected($currentTheme['preset_key'] === $presetKey)>{{ $preset['label'] }}</option>
                @endforeach
            </select>
            <small>Используется как стартовая точка для палитры.</small>
        </div>
        <div class="field">
            <label for="body_font">Шрифт текста</label>
            <select id="body_font" name="body_font" data-theme-field="body_font">
                @foreach($fontOptions as $fontKey => $font)
                    <option value="{{ $fontKey }}" @selected($currentTheme['body_font'] === $fontKey)>{{ $font['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="heading_font">Шрифт заголовков</label>
            <select id="heading_font" name="heading_font" data-theme-field="heading_font">
                @foreach($fontOptions as $fontKey => $font)
                    <option value="{{ $fontKey }}" @selected($currentTheme['heading_font'] === $fontKey)>{{ $font['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="field">
        <label for="mono_font">Моноширинный шрифт</label>
        <select id="mono_font" name="mono_font" data-theme-field="mono_font">
            @foreach($fontOptions as $fontKey => $font)
                <option value="{{ $fontKey }}" @selected($currentTheme['mono_font'] === $fontKey)>{{ $font['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="theme-color-grid">
        @foreach($colorKeys as $colorKey)
            @php
                $value = strtoupper((string) ($currentTheme['colors'][$colorKey] ?? '#000000'));
                $meta = $colorMeta[$colorKey] ?? ['label' => $colorKey, 'hint' => ''];
            @endphp
            <div class="theme-color-row">
                <label for="color_{{ $colorKey }}">{{ $meta['label'] }}</label>
                <div class="pickers">
                    <input type="color" value="{{ $value }}" data-color-sync="colors[{{ $colorKey }}]">
                    <input
                        type="text"
                        id="color_{{ $colorKey }}"
                        name="colors[{{ $colorKey }}]"
                        value="{{ $value }}"
                        maxlength="7"
                        pattern="^#[0-9A-Fa-f]{6}$"
                        data-theme-color="{{ $colorKey }}"
                    >
                </div>
                <small>{{ $meta['hint'] }}</small>
            </div>
        @endforeach
    </div>

    <div class="actions" style="margin-top:14px;">
        <button class="btn btn-primary" type="submit">Сохранить тему</button>
        <button class="btn" type="button" id="theme-reset-form">Сбросить форму к сохранённой</button>
    </div>
</form>
