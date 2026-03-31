<?php

namespace App\Modules\Core\Services;

class ThemeCssRenderer
{
    private ?string $baseCssCache = null;

    /**
     * @param  array<string, string>  $themeColors
     * @param  array<string, string>  $themeFonts
     */
    public function render(array $themeColors, array $themeFonts): string
    {
        return trim($this->baseCss())."\n\n".trim($this->dynamicCss($themeColors, $themeFonts));
    }

    private function baseCss(): string
    {
        if ($this->baseCssCache !== null) {
            return $this->baseCssCache;
        }

        $path = resource_path('css/cms/theme-base.css');
        $this->baseCssCache = is_file($path) ? (string) file_get_contents($path) : '';

        return $this->baseCssCache;
    }

    /**
     * @param  array<string, string>  $themeColors
     * @param  array<string, string>  $themeFonts
     */
    private function dynamicCss(array $themeColors, array $themeFonts): string
    {
        $bg = $themeColors['bg'] ?? '#F4F1EA';
        $surface = $themeColors['surface'] ?? '#FFFFFF';
        $surfaceTint = $themeColors['surface_tint'] ?? '#FFFFFF';
        $text = $themeColors['text'] ?? '#111827';
        $muted = $themeColors['muted'] ?? '#5B6475';
        $line = $themeColors['line'] ?? '#E0D8CC';
        $lineStrong = $themeColors['line_strong'] ?? '#CFC5B6';
        $brand = $themeColors['brand'] ?? '#D9472B';
        $brandDeep = $themeColors['brand_deep'] ?? '#B5361D';
        $brandAlt = $themeColors['brand_alt'] ?? '#EF7F1A';
        $accent = $themeColors['accent'] ?? '#0F172A';
        $accent2 = $themeColors['accent_2'] ?? '#1D3557';
        $success = $themeColors['success'] ?? '#0F766E';
        $bgStart = $themeColors['bg_start'] ?? '#F7F3EB';
        $bgEnd = $themeColors['bg_end'] ?? '#F1EDE4';

        $bodyFont = $themeFonts['body'] ?? '"Manrope", "Segoe UI", sans-serif';
        $headingFont = $themeFonts['heading'] ?? '"Space Grotesk", "Segoe UI", sans-serif';
        $monoFont = $themeFonts['mono'] ?? '"IBM Plex Mono", "SFMono-Regular", Menlo, monospace';

        return <<<CSS
:root {
    --bg: {$bg};
    --surface: {$surface};
    --surface-strong: {$surface};
    --ink: {$text};
    --muted: {$muted};
    --line: {$this->hexToRgba($line, 0.72)};
    --line-strong: {$this->hexToRgba($lineStrong, 0.9)};
    --brand: {$brand};
    --brand-deep: {$brandDeep};
    --brand-alt: {$brandAlt};
    --brand-soft: {$this->hexToRgba($brand, 0.12)};
    --accent: {$accent};
    --accent-2: {$accent2};
    --success: {$success};
    --font-body: {$bodyFont};
    --font-heading: {$headingFont};
    --font-mono: {$monoFont};
}

body {
    background:
        radial-gradient(900px 520px at 0% 0%, {$this->hexToRgba($brand, 0.12)}, transparent 70%),
        radial-gradient(760px 460px at 100% 0%, {$this->hexToRgba($accent2, 0.12)}, transparent 72%),
        radial-gradient(640px 420px at 90% 100%, {$this->hexToRgba($success, 0.08)}, transparent 70%),
        linear-gradient(180deg, {$bgStart} 0%, {$bgEnd} 100%);
    font-family: var(--font-body) !important;
}

.topbar,
.site-footer {
    background: {$this->hexToRgba($surfaceTint, 0.58)};
}

.hero-shell {
    background:
        linear-gradient(180deg, {$this->hexToRgba($surface, 0.88)}, {$this->hexToRgba($surfaceTint, 0.72)}),
        radial-gradient(1000px 500px at 10% 0%, {$this->hexToRgba($brand, 0.14)}, transparent 65%),
        radial-gradient(800px 400px at 100% 10%, {$this->hexToRgba($accent2, 0.14)}, transparent 70%);
}

.brand-mark,
.button-primary,
.cms-cta {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-alt) 100%);
}

.brand-title,
.hero-title,
.hero-panel h3,
.page-title,
.post-card-title,
.section-header h2,
.content-prose h1,
.content-prose h2,
.content-prose h3,
.content-prose h4,
.content-prose h5,
.content-prose h6 {
    font-family: var(--font-heading) !important;
}

.content-prose code,
.content-prose pre,
.mono {
    font-family: var(--font-mono) !important;
}

.hero-panel,
.hero-kpi,
.surface,
.post-card,
.post-card.featured,
.pager,
.content-prose table,
.content-prose figure,
.cms-faq details {
    background-color: {$this->hexToRgba($surfaceTint, 0.68)};
}
CSS;
    }

    private function hexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) !== 6 || preg_match('/^[0-9A-Fa-f]{6}$/', $hex) !== 1) {
            return 'rgba(0,0,0,'.max(0, min(1, $alpha)).')';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d,%d,%d,%.3F)', $r, $g, $b, max(0, min(1, $alpha)));
    }
}
