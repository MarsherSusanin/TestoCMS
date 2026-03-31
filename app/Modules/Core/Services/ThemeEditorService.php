<?php

namespace App\Modules\Core\Services;

use App\Models\ThemeSetting;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Core\Contracts\ThemeEditorServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\Request;

class ThemeEditorService implements ThemeEditorServiceContract
{
    public function __construct(
        private readonly ThemeSettingsService $themeSettings,
        private readonly PageCacheService $pageCacheService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function editData(Request $request): array
    {
        $theme = $this->themeSettings->resolvedTheme();
        $currentTheme = $this->currentThemeFromRequest($request, $theme);

        return [
            'theme' => $theme,
            'presets' => $this->themeSettings->presets(),
            'fontOptions' => $this->themeSettings->fontOptions(),
            'colorMeta' => $this->themeSettings->colorFieldMeta(),
            'colorKeys' => $this->themeSettings->colorKeys(),
            'currentTheme' => $currentTheme,
            'themeBootPayload' => [
                'savedTheme' => $theme,
                'currentTheme' => $currentTheme,
                'presets' => $this->themeSettings->presets(),
                'fontOptions' => $this->themeSettings->fontOptions(),
            ],
        ];
    }

    public function save(array $validated, Request $request): ThemeSetting
    {
        $payload = $this->themeSettings->normalizeForSave($validated);
        $record = $this->themeSettings->save($payload, $request->user()?->id);
        $this->pageCacheService->flushAll();

        $this->auditLogger->log('theme.update.web', $record, [
            'preset_key' => $payload['preset_key'],
            'fonts' => [
                'body' => $payload['body_font'],
                'heading' => $payload['heading_font'],
                'mono' => $payload['mono_font'],
            ],
        ], $request);

        return $record;
    }

    public function applyPreset(string $presetKey, Request $request): ThemeSetting
    {
        $record = $this->themeSettings->resetToPreset($presetKey, $request->user()?->id);
        $this->pageCacheService->flushAll();

        $this->auditLogger->log('theme.apply_preset.web', $record, [
            'preset_key' => $presetKey,
        ], $request);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    private function currentThemeFromRequest(Request $request, array $theme): array
    {
        $hasOldInput = $request->session()->hasOldInput();
        if (! $hasOldInput) {
            return $theme;
        }

        return [
            'preset_key' => (string) $request->old('preset_key', $theme['preset_key']),
            'body_font' => (string) $request->old('body_font', $theme['body_font']),
            'heading_font' => (string) $request->old('heading_font', $theme['heading_font']),
            'mono_font' => (string) $request->old('mono_font', $theme['mono_font']),
            'colors' => collect($this->themeSettings->colorKeys())
                ->mapWithKeys(fn (string $key): array => [$key => $request->old("colors.$key", $theme['colors'][$key] ?? '#000000')])
                ->all(),
        ];
    }
}
