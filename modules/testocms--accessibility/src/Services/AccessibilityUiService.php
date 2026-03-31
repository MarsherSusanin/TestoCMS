<?php

namespace TestoCms\Accessibility\Services;

class AccessibilityUiService
{
    public function storageKey(): string
    {
        return 'testocms:a11y';
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'enabled' => false,
            'panelOpen' => false,
            'contrast' => 'bw',
            'fontScale' => 120,
            'imageMode' => 'normal',
            'speechEnabled' => false,
            'letterSpacing' => 'medium',
            'lineHeight' => 'medium',
            'fontFamily' => 'sans',
            'embedsEnabled' => true,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function fontScaleOptions(): array
    {
        return [100, 110, 120, 130, 140];
    }

    /**
     * @return array<string, array{label: string, theme: array<string, string>}>
     */
    public function contrastPresets(string $locale): array
    {
        $isRu = $locale === 'ru';

        return [
            'bw' => [
                'label' => $isRu ? 'Черный по белому' : 'Black on white',
                'theme' => [
                    'bg' => '#FFFFFF',
                    'surface' => '#FFFFFF',
                    'surfaceStrong' => '#FFFFFF',
                    'ink' => '#000000',
                    'muted' => '#1C1C1C',
                    'line' => 'rgba(0, 0, 0, 0.50)',
                    'lineStrong' => 'rgba(0, 0, 0, 0.78)',
                    'brand' => '#000000',
                    'brandDeep' => '#000000',
                    'brandAlt' => '#222222',
                    'brandSoft' => 'rgba(0, 0, 0, 0.16)',
                    'accent' => '#000000',
                    'accent2' => '#111111',
                    'success' => '#000000',
                ],
            ],
            'wb' => [
                'label' => $isRu ? 'Белый по черному' : 'White on black',
                'theme' => [
                    'bg' => '#000000',
                    'surface' => '#000000',
                    'surfaceStrong' => '#050505',
                    'ink' => '#FFFFFF',
                    'muted' => '#F5F5F5',
                    'line' => 'rgba(255, 255, 255, 0.48)',
                    'lineStrong' => 'rgba(255, 255, 255, 0.78)',
                    'brand' => '#FFFFFF',
                    'brandDeep' => '#FFFFFF',
                    'brandAlt' => '#E5E5E5',
                    'brandSoft' => 'rgba(255, 255, 255, 0.16)',
                    'accent' => '#FFFFFF',
                    'accent2' => '#E5E5E5',
                    'success' => '#FFFFFF',
                ],
            ],
            'blue_cyan' => [
                'label' => $isRu ? 'Темно-синий по голубому' : 'Dark blue on light blue',
                'theme' => [
                    'bg' => '#D9F1FF',
                    'surface' => '#EAF7FF',
                    'surfaceStrong' => '#FFFFFF',
                    'ink' => '#082B73',
                    'muted' => '#103A8C',
                    'line' => 'rgba(8, 43, 115, 0.34)',
                    'lineStrong' => 'rgba(8, 43, 115, 0.58)',
                    'brand' => '#082B73',
                    'brandDeep' => '#082B73',
                    'brandAlt' => '#0B4CC2',
                    'brandSoft' => 'rgba(8, 43, 115, 0.16)',
                    'accent' => '#082B73',
                    'accent2' => '#0B4CC2',
                    'success' => '#0F5CC0',
                ],
            ],
            'brown_beige' => [
                'label' => $isRu ? 'Коричневый по бежевому' : 'Brown on beige',
                'theme' => [
                    'bg' => '#F5E9D3',
                    'surface' => '#FFF7EA',
                    'surfaceStrong' => '#FFFBF4',
                    'ink' => '#4A2F12',
                    'muted' => '#6B4A28',
                    'line' => 'rgba(74, 47, 18, 0.28)',
                    'lineStrong' => 'rgba(74, 47, 18, 0.48)',
                    'brand' => '#4A2F12',
                    'brandDeep' => '#4A2F12',
                    'brandAlt' => '#7B4F1C',
                    'brandSoft' => 'rgba(74, 47, 18, 0.16)',
                    'accent' => '#4A2F12',
                    'accent2' => '#7B4F1C',
                    'success' => '#6B4A28',
                ],
            ],
            'green_brown' => [
                'label' => $isRu ? 'Зеленый по темно-коричневому' : 'Green on dark brown',
                'theme' => [
                    'bg' => '#24180F',
                    'surface' => '#2D1F14',
                    'surfaceStrong' => '#352518',
                    'ink' => '#C7FF7A',
                    'muted' => '#DBFFAA',
                    'line' => 'rgba(199, 255, 122, 0.32)',
                    'lineStrong' => 'rgba(199, 255, 122, 0.56)',
                    'brand' => '#C7FF7A',
                    'brandDeep' => '#C7FF7A',
                    'brandAlt' => '#E6FFB8',
                    'brandSoft' => 'rgba(199, 255, 122, 0.18)',
                    'accent' => '#C7FF7A',
                    'accent2' => '#E6FFB8',
                    'success' => '#D7FF99',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, value: string}>
     */
    public function letterSpacingOptions(string $locale): array
    {
        $isRu = $locale === 'ru';

        return [
            'standard' => ['label' => $isRu ? 'Стандартный' : 'Standard', 'value' => '0em'],
            'medium' => ['label' => $isRu ? 'Средний' : 'Medium', 'value' => '0.04em'],
            'large' => ['label' => $isRu ? 'Большой' : 'Large', 'value' => '0.08em'],
        ];
    }

    /**
     * @return array<string, array{label: string, value: string}>
     */
    public function lineHeightOptions(string $locale): array
    {
        $isRu = $locale === 'ru';

        return [
            'standard' => ['label' => $isRu ? 'Стандартный' : 'Standard', 'value' => '1.55'],
            'medium' => ['label' => $isRu ? 'Средний' : 'Medium', 'value' => '1.75'],
            'large' => ['label' => $isRu ? 'Большой' : 'Large', 'value' => '1.95'],
        ];
    }

    /**
     * @return array<string, array{label: string, bodyStack: string, headingStack: string}>
     */
    public function fontFamilies(string $locale): array
    {
        $isRu = $locale === 'ru';

        return [
            'sans' => [
                'label' => $isRu ? 'Без засечек' : 'Sans-serif',
                'bodyStack' => '"Arial", "Helvetica Neue", "Segoe UI", sans-serif',
                'headingStack' => '"Arial", "Helvetica Neue", "Segoe UI", sans-serif',
            ],
            'serif' => [
                'label' => $isRu ? 'С засечками' : 'Serif',
                'bodyStack' => '"Georgia", "Times New Roman", serif',
                'headingStack' => '"Georgia", "Times New Roman", serif',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string}>
     */
    public function imageModes(string $locale): array
    {
        $isRu = $locale === 'ru';

        return [
            'normal' => ['label' => $isRu ? 'Оставить как есть' : 'Keep images'],
            'hidden' => ['label' => $isRu ? 'Выключить' : 'Hide images'],
            'grayscale' => ['label' => $isRu ? 'Ч/б фильтр' : 'Grayscale'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function labels(string $locale): array
    {
        if ($locale === 'ru') {
            return [
                'header_button' => 'Версия для слабовидящих',
                'panel_title' => 'Режим для слабовидящих',
                'font_size' => 'Размер шрифта',
                'font_size_decrease' => 'Уменьшить шрифт',
                'font_size_increase' => 'Увеличить шрифт',
                'contrast' => 'Контраст',
                'images' => 'Изображения',
                'speech' => 'Синтез речи',
                'speech_status_off' => 'Озвучка выключена',
                'speech_status_on' => 'Озвучка активна',
                'speech_status_paused' => 'Озвучка на паузе',
                'speech_status_unavailable' => 'Web Speech API недоступен в этом браузере',
                'letter_spacing' => 'Межбуквенное расстояние',
                'line_height' => 'Межстрочный интервал',
                'font_family' => 'Шрифт',
                'embeds' => 'Встроенные элементы',
                'on' => 'Включить',
                'off' => 'Выключить',
                'play' => 'Старт',
                'pause' => 'Пауза',
                'resume' => 'Продолжить',
                'stop' => 'Стоп',
                'exit_normal' => 'Выйти в обычный режим',
            ];
        }

        return [
            'header_button' => 'Accessible mode',
            'panel_title' => 'Low-vision mode',
            'font_size' => 'Font size',
            'font_size_decrease' => 'Decrease font size',
            'font_size_increase' => 'Increase font size',
            'contrast' => 'Contrast',
            'images' => 'Images',
            'speech' => 'Speech synthesis',
            'speech_status_off' => 'Speech is off',
            'speech_status_on' => 'Speech is active',
            'speech_status_paused' => 'Speech is paused',
            'speech_status_unavailable' => 'Web Speech API is not available in this browser',
            'letter_spacing' => 'Letter spacing',
            'line_height' => 'Line height',
            'font_family' => 'Font family',
            'embeds' => 'Embedded elements',
            'on' => 'On',
            'off' => 'Off',
            'play' => 'Play',
            'pause' => 'Pause',
            'resume' => 'Resume',
            'stop' => 'Stop',
            'exit_normal' => 'Exit to normal mode',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function viewData(array $context = []): array
    {
        $locale = $this->locale($context);

        return [
            'a11y' => [
                'asset_url' => asset('modules/testocms--accessibility/accessibility-public.js'),
                'boot_payload' => $this->bootPayload($locale),
                'contrast_presets' => $this->contrastPresets($locale),
                'default_state' => $this->defaultState(),
                'font_families' => $this->fontFamilies($locale),
                'font_scale_options' => $this->fontScaleOptions(),
                'image_modes' => $this->imageModes($locale),
                'labels' => $this->labels($locale),
                'letter_spacing_options' => $this->letterSpacingOptions($locale),
                'line_height_options' => $this->lineHeightOptions($locale),
                'locale' => $locale,
                'storage_key' => $this->storageKey(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bootPayload(string $locale): array
    {
        return [
            'storageKey' => $this->storageKey(),
            'defaultState' => $this->defaultState(),
            'fontScaleOptions' => $this->fontScaleOptions(),
            'maps' => [
                'contrastPresets' => $this->contrastPresets($locale),
                'fontFamilies' => $this->fontFamilies($locale),
                'imageModes' => $this->imageModes($locale),
                'letterSpacing' => $this->letterSpacingOptions($locale),
                'lineHeights' => $this->lineHeightOptions($locale),
            ],
            'labels' => $this->labels($locale),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function locale(array $context): string
    {
        $locale = strtolower(trim((string) ($context['current_locale'] ?? app()->getLocale())));

        return $locale === 'ru' ? 'ru' : 'en';
    }
}
