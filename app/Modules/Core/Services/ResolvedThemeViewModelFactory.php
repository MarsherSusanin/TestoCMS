<?php

namespace App\Modules\Core\Services;

class ResolvedThemeViewModelFactory
{
    public function __construct(
        private readonly ThemeSettingsService $themeSettings,
        private readonly ThemeCssRenderer $themeCssRenderer,
    ) {}

    /**
     * @param  array<string, mixed>|null  $siteTheme
     * @return array<string, mixed>
     */
    public function build(?array $siteTheme = null): array
    {
        $resolvedTheme = is_array($siteTheme) ? $siteTheme : $this->themeSettings->resolvedTheme();
        $themeColors = is_array($resolvedTheme['colors'] ?? null) ? $resolvedTheme['colors'] : [];
        $themeFonts = is_array($resolvedTheme['font_stacks'] ?? null) ? $resolvedTheme['font_stacks'] : [];
        $themeGoogleFontsUrl = is_string($resolvedTheme['google_fonts_url'] ?? null) ? $resolvedTheme['google_fonts_url'] : null;

        return [
            'site_theme' => $resolvedTheme,
            'theme_colors' => $themeColors,
            'theme_fonts' => $themeFonts,
            'theme_google_fonts_url' => $themeGoogleFontsUrl,
            'theme_color' => (string) ($themeColors['accent'] ?? '#0f172a'),
            'theme_css' => $this->themeCssRenderer->render($themeColors, $themeFonts),
            // Public pages inline only the small per-theme tokens and load the
            // large structural sheet as an external, immutable, cacheable file.
            'theme_css_dynamic' => $this->themeCssRenderer->dynamicCss($themeColors, $themeFonts),
            'theme_base_css_url' => url('/cms/theme-base.css').'?v='.$this->themeCssRenderer->baseCssHash(),
        ];
    }
}
