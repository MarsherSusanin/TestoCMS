<?php

namespace App\Modules\Core\Services;

use App\Models\ThemeSetting;

class ThemeSettingsService
{
    public function __construct(
        private readonly ThemeCatalogService $catalog,
        private readonly ThemeSettingStore $store,
    ) {
    }

    private ?array $cachedResolvedTheme = null;

    /**
     * @return array<string, array{label:string, stack:string, google?:string}>
     */
    public function fontOptions(): array
    {
        return $this->catalog->fontOptions();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function presets(): array
    {
        return $this->catalog->presets();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultTheme(): array
    {
        return $this->catalog->defaultTheme();
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedTheme(): array
    {
        if ($this->cachedResolvedTheme !== null) {
            return $this->cachedResolvedTheme;
        }

        $defaults = $this->defaultTheme();
        $payload = $this->store->loadDefaultPayload();
        $presets = $this->presets();
        $fontOptions = $this->fontOptions();

        $presetKey = (string) ($payload['preset_key'] ?? $defaults['preset_key']);
        if (! isset($presets[$presetKey])) {
            $presetKey = (string) $defaults['preset_key'];
        }

        $preset = $presets[$presetKey];
        $baseColors = is_array($preset['colors'] ?? null) ? $preset['colors'] : [];
        $storedColors = is_array($payload['colors'] ?? null) ? $payload['colors'] : [];

        $colors = [];
        foreach ($this->colorKeys() as $key) {
            $candidate = (string) ($storedColors[$key] ?? $baseColors[$key] ?? ($defaults['colors'][$key] ?? ''));
            $colors[$key] = $this->normalizeHex($candidate) ?? (string) ($baseColors[$key] ?? $defaults['colors'][$key]);
        }

        $bodyFont = $this->resolveFontKey((string) ($payload['body_font'] ?? $preset['body_font'] ?? $defaults['body_font']));
        $headingFont = $this->resolveFontKey((string) ($payload['heading_font'] ?? $preset['heading_font'] ?? $defaults['heading_font']));
        $monoFont = $this->resolveFontKey((string) ($payload['mono_font'] ?? $preset['mono_font'] ?? $defaults['mono_font']));

        return $this->cachedResolvedTheme = [
            'preset_key' => $presetKey,
            'preset' => $preset,
            'colors' => $colors,
            'body_font' => $bodyFont,
            'heading_font' => $headingFont,
            'mono_font' => $monoFont,
            'font_stacks' => [
                'body' => $fontOptions[$bodyFont]['stack'],
                'heading' => $fontOptions[$headingFont]['stack'],
                'mono' => $fontOptions[$monoFont]['stack'],
            ],
            'google_fonts_url' => $this->catalog->buildGoogleFontsUrl([$bodyFont, $headingFont, $monoFont]),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalizeForSave(array $input): array
    {
        $defaults = $this->defaultTheme();
        $presets = $this->presets();
        $presetKey = (string) ($input['preset_key'] ?? $defaults['preset_key']);
        if (! isset($presets[$presetKey])) {
            $presetKey = (string) $defaults['preset_key'];
        }

        $colorsInput = is_array($input['colors'] ?? null) ? $input['colors'] : [];
        $colors = [];
        foreach ($this->colorKeys() as $key) {
            $normalized = $this->normalizeHex((string) ($colorsInput[$key] ?? ''));
            $colors[$key] = $normalized ?? (string) ($presets[$presetKey]['colors'][$key] ?? $defaults['colors'][$key]);
        }

        return [
            'preset_key' => $presetKey,
            'body_font' => $this->resolveFontKey((string) ($input['body_font'] ?? $defaults['body_font'])),
            'heading_font' => $this->resolveFontKey((string) ($input['heading_font'] ?? $defaults['heading_font'])),
            'mono_font' => $this->resolveFontKey((string) ($input['mono_font'] ?? $defaults['mono_font'])),
            'colors' => $colors,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(array $payload, ?int $actorId = null): ThemeSetting
    {
        $themeSetting = $this->store->saveDefaultPayload($payload, $actorId);
        $this->cachedResolvedTheme = null;

        return $themeSetting;
    }

    public function resetToPreset(string $presetKey, ?int $actorId = null): ThemeSetting
    {
        $presets = $this->presets();
        if (! isset($presets[$presetKey])) {
            $presetKey = (string) $this->defaultTheme()['preset_key'];
        }

        $preset = $presets[$presetKey];

        return $this->save([
            'preset_key' => $presetKey,
            'body_font' => $preset['body_font'],
            'heading_font' => $preset['heading_font'],
            'mono_font' => $preset['mono_font'],
            'colors' => $preset['colors'],
        ], $actorId);
    }

    /**
     * @return array<int, string>
     */
    public function presetKeys(): array
    {
        return array_keys($this->presets());
    }

    /**
     * @return array<int, string>
     */
    public function fontKeys(): array
    {
        return array_keys($this->fontOptions());
    }

    /**
     * @return array<int, string>
     */
    public function colorKeys(): array
    {
        return $this->catalog->colorKeys();
    }

    /**
     * @return array<string, array{label:string, hint:string}>
     */
    public function colorFieldMeta(): array
    {
        return $this->catalog->colorFieldMeta();
    }

    private function resolveFontKey(string $fontKey): string
    {
        $fontOptions = $this->fontOptions();

        return array_key_exists($fontKey, $fontOptions)
            ? $fontKey
            : (string) array_key_first($fontOptions);
    }

    private function normalizeHex(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^#[0-9A-F]{6}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
