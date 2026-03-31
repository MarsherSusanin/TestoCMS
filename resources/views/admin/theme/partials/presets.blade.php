<section class="panel">
    <div class="inline" style="justify-content:space-between; margin-bottom:10px;">
        <h2 style="margin:0; font-size:18px;">Пресеты</h2>
        <span class="muted">Текущий: <strong>{{ $presets[$theme['preset_key']]['label'] ?? $theme['preset_key'] }}</strong></span>
    </div>
    <div class="theme-presets">
        @foreach($presets as $presetKey => $preset)
            <div class="theme-preset-card {{ $theme['preset_key'] === $presetKey ? 'active' : '' }}" data-preset-card="{{ $presetKey }}">
                <h3>{{ $preset['label'] }}</h3>
                <p>{{ $preset['description'] }}</p>
                <div class="theme-swatches">
                    @foreach(['bg_start', 'bg_end', 'surface', 'brand', 'brand_alt', 'accent'] as $swatchKey)
                        <span style="background: {{ $preset['colors'][$swatchKey] }}"></span>
                    @endforeach
                </div>
                <div class="inline">
                    <button type="button" class="btn btn-small" data-apply-preset-to-form="{{ $presetKey }}">Заполнить форму</button>
                    <form method="POST" action="{{ route('admin.theme.apply-preset') }}" style="display:inline;">
                        @csrf
                        <input type="hidden" name="preset_key" value="{{ $presetKey }}">
                        <button type="submit" class="btn btn-small btn-primary">Применить и сохранить</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</section>
