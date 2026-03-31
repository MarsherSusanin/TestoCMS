<?php

namespace App\Modules\Core\Services;

class ThemeCatalogService
{
    /**
     * @return array<string, array{label:string, stack:string, google?:string}>
     */
    public function fontOptions(): array
    {
        return [
            'manrope' => ['label' => 'Manrope', 'stack' => '"Manrope", "Segoe UI", system-ui, sans-serif', 'google' => 'family=Manrope:wght@400;500;600;700;800'],
            'rubik' => ['label' => 'Rubik', 'stack' => '"Rubik", "Segoe UI", system-ui, sans-serif', 'google' => 'family=Rubik:wght@400;500;700;800'],
            'source_sans_3' => ['label' => 'Source Sans 3', 'stack' => '"Source Sans 3", "Segoe UI", system-ui, sans-serif', 'google' => 'family=Source+Sans+3:wght@400;500;600;700'],
            'inter' => ['label' => 'Inter', 'stack' => '"Inter", "Segoe UI", system-ui, sans-serif', 'google' => 'family=Inter:wght@400;500;600;700;800'],
            'space_grotesk' => ['label' => 'Space Grotesk', 'stack' => '"Space Grotesk", "Avenir Next Condensed", "Segoe UI", sans-serif', 'google' => 'family=Space+Grotesk:wght@400;500;700'],
            'sora' => ['label' => 'Sora', 'stack' => '"Sora", "Segoe UI", system-ui, sans-serif', 'google' => 'family=Sora:wght@400;500;600;700;800'],
            'merriweather' => ['label' => 'Merriweather', 'stack' => '"Merriweather", Georgia, serif', 'google' => 'family=Merriweather:wght@400;700;900'],
            'playfair_display' => ['label' => 'Playfair Display', 'stack' => '"Playfair Display", Georgia, serif', 'google' => 'family=Playfair+Display:wght@600;700;800'],
            'ibm_plex_mono' => ['label' => 'IBM Plex Mono', 'stack' => '"IBM Plex Mono", "SFMono-Regular", Menlo, Consolas, monospace', 'google' => 'family=IBM+Plex+Mono:wght@400;500;700'],
            'jetbrains_mono' => ['label' => 'JetBrains Mono', 'stack' => '"JetBrains Mono", "SFMono-Regular", Menlo, Consolas, monospace', 'google' => 'family=JetBrains+Mono:wght@400;500;700'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function presets(): array
    {
        return [
            'warm_editorial' => [
                'label' => 'Теплая редакционная',
                'description' => 'Тёплый бумажный фон, насыщенный красный акцент, выразительные заголовки.',
                'body_font' => 'manrope',
                'heading_font' => 'space_grotesk',
                'mono_font' => 'ibm_plex_mono',
                'colors' => ['bg' => '#F4F1EA', 'bg_start' => '#F7F3EB', 'bg_end' => '#F1EDE4', 'surface' => '#FFFFFF', 'surface_tint' => '#FFFFFF', 'text' => '#111827', 'muted' => '#5B6475', 'line' => '#E0D8CC', 'line_strong' => '#CFC5B6', 'brand' => '#D9472B', 'brand_deep' => '#B5361D', 'brand_alt' => '#EF7F1A', 'accent' => '#0F172A', 'accent_2' => '#1D3557', 'success' => '#0F766E'],
            ],
            'cobalt_glass' => [
                'label' => 'Кобальтовое стекло',
                'description' => 'Холодный голубой акцент и чистая типографика для tech-бренда.',
                'body_font' => 'inter',
                'heading_font' => 'sora',
                'mono_font' => 'jetbrains_mono',
                'colors' => ['bg' => '#EEF4FF', 'bg_start' => '#F4F8FF', 'bg_end' => '#EAF1FF', 'surface' => '#FFFFFF', 'surface_tint' => '#F8FBFF', 'text' => '#0F172A', 'muted' => '#5D6B83', 'line' => '#D8E4FF', 'line_strong' => '#B9D0FF', 'brand' => '#2563EB', 'brand_deep' => '#1D4ED8', 'brand_alt' => '#38BDF8', 'accent' => '#0B1220', 'accent_2' => '#173A8A', 'success' => '#0E7490'],
            ],
            'sage_studio' => [
                'label' => 'Шалфейная студия',
                'description' => 'Мягкая природная палитра и спокойный редакционный тон.',
                'body_font' => 'source_sans_3',
                'heading_font' => 'merriweather',
                'mono_font' => 'ibm_plex_mono',
                'colors' => ['bg' => '#EFF3EC', 'bg_start' => '#F5F8F2', 'bg_end' => '#E9EFE6', 'surface' => '#FFFFFF', 'surface_tint' => '#F9FBF8', 'text' => '#1C2A23', 'muted' => '#5F7067', 'line' => '#D4DED4', 'line_strong' => '#B7C8BC', 'brand' => '#2F7A5D', 'brand_deep' => '#1F5D45', 'brand_alt' => '#7FB069', 'accent' => '#15211D', 'accent_2' => '#264E45', 'success' => '#177A5A'],
            ],
            'editorial_peach' => [
                'label' => 'Персиковый журнал',
                'description' => 'Контрастные заголовки и мягкие коралловые акценты.',
                'body_font' => 'rubik',
                'heading_font' => 'playfair_display',
                'mono_font' => 'jetbrains_mono',
                'colors' => ['bg' => '#FBF0EA', 'bg_start' => '#FFF6F1', 'bg_end' => '#F8E8DE', 'surface' => '#FFFFFF', 'surface_tint' => '#FFFDFC', 'text' => '#231815', 'muted' => '#75605A', 'line' => '#E9D6CD', 'line_strong' => '#DDBFB2', 'brand' => '#D26A4B', 'brand_deep' => '#B75035', 'brand_alt' => '#F49C5C', 'accent' => '#1F1720', 'accent_2' => '#4D2437', 'success' => '#0F766E'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultTheme(): array
    {
        $presetKey = 'warm_editorial';
        $preset = $this->presets()[$presetKey];

        return [
            'preset_key' => $presetKey,
            'body_font' => $preset['body_font'],
            'heading_font' => $preset['heading_font'],
            'mono_font' => $preset['mono_font'],
            'colors' => $preset['colors'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function colorKeys(): array
    {
        return ['bg', 'bg_start', 'bg_end', 'surface', 'surface_tint', 'text', 'muted', 'line', 'line_strong', 'brand', 'brand_deep', 'brand_alt', 'accent', 'accent_2', 'success'];
    }

    /**
     * @return array<string, array{label:string, hint:string}>
     */
    public function colorFieldMeta(): array
    {
        return [
            'bg' => ['label' => 'Базовый фон', 'hint' => 'Основной цвет страницы'],
            'bg_start' => ['label' => 'Градиент (старт)', 'hint' => 'Верхняя часть фонового градиента'],
            'bg_end' => ['label' => 'Градиент (финиш)', 'hint' => 'Нижняя часть фонового градиента'],
            'surface' => ['label' => 'Панели', 'hint' => 'Основной цвет карточек и панелей'],
            'surface_tint' => ['label' => 'Тон поверхности', 'hint' => 'Подкраска стеклянных поверхностей'],
            'text' => ['label' => 'Текст', 'hint' => 'Основной цвет текста'],
            'muted' => ['label' => 'Вторичный текст', 'hint' => 'Подписи и второстепенная информация'],
            'line' => ['label' => 'Границы', 'hint' => 'Основные рамки и разделители'],
            'line_strong' => ['label' => 'Акцентные границы', 'hint' => 'Более заметные линии'],
            'brand' => ['label' => 'Бренд (основной)', 'hint' => 'Главные CTA и акценты'],
            'brand_deep' => ['label' => 'Бренд (глубокий)', 'hint' => 'Тёмный вариант бренда'],
            'brand_alt' => ['label' => 'Бренд (вторичный)', 'hint' => 'Градиенты и вторые акценты'],
            'accent' => ['label' => 'Акцент тёмный', 'hint' => 'Тёмные навигационные элементы'],
            'accent_2' => ['label' => 'Акцент дополнительный', 'hint' => 'Вторичный тёмный оттенок'],
            'success' => ['label' => 'Success', 'hint' => 'Сервисный цвет (статус/подсветка)'],
        ];
    }

    /**
     * @param array<int, string> $fontKeys
     */
    public function buildGoogleFontsUrl(array $fontKeys): ?string
    {
        $fontOptions = $this->fontOptions();
        $queries = [];

        foreach (array_unique($fontKeys) as $fontKey) {
            $google = $fontOptions[$fontKey]['google'] ?? null;
            if (is_string($google) && $google !== '') {
                $queries[] = $google;
            }
        }

        if ($queries === []) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?'.implode('&', $queries).'&display=swap';
    }
}
