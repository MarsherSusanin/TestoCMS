<?php

namespace App\Modules\Core\Services;

class SiteChromeNormalizerService
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'version' => 1,
            'header' => [
                'enabled' => true,
                'variant' => 'split_nav',
                'show_brand_subtitle' => true,
                'show_locale_switcher' => true,
                'show_search' => true,
                'search_placement' => 'header',
                'nav_items' => [
                    ['id' => 'home', 'enabled' => true, 'url' => '/{locale}', 'new_tab' => false, 'nofollow' => false, 'label_translations' => ['ru' => 'Главная', 'en' => 'Home']],
                    ['id' => 'blog', 'enabled' => true, 'url' => '/{locale}/'.trim((string) config('cms.post_url_prefix', 'blog'), '/'), 'new_tab' => false, 'nofollow' => false, 'label_translations' => ['ru' => 'Блог', 'en' => 'Blog']],
                    ['id' => 'rss', 'enabled' => true, 'url' => '/feed/{locale}.xml', 'new_tab' => false, 'nofollow' => false, 'label_translations' => ['ru' => 'RSS', 'en' => 'RSS']],
                ],
                'cta_buttons' => [],
            ],
            'footer' => [
                'enabled' => true,
                'variant' => 'inline',
                'show_brand' => true,
                'show_tagline' => true,
                'tagline_translations' => ['ru' => 'SEO-first CMS на Laravel', 'en' => 'SEO-first CMS on Laravel'],
                'links' => [
                    ['id' => 'footer-home', 'enabled' => true, 'url' => '/{locale}', 'new_tab' => false, 'nofollow' => false, 'label_translations' => ['ru' => 'Главная', 'en' => 'Home']],
                    ['id' => 'footer-blog', 'enabled' => true, 'url' => '/{locale}/'.trim((string) config('cms.post_url_prefix', 'blog'), '/'), 'new_tab' => false, 'nofollow' => false, 'label_translations' => ['ru' => 'Блог', 'en' => 'Blog']],
                    ['id' => 'footer-rss', 'enabled' => true, 'url' => '/feed/{locale}.xml', 'new_tab' => false, 'nofollow' => false, 'label_translations' => ['ru' => 'RSS', 'en' => 'RSS']],
                ],
                'social_links' => [],
                'legal_links' => [],
            ],
            'search' => [
                'enabled' => true,
                'path_slug' => 'search',
                'scope_default' => 'all',
                'results_per_page' => 12,
                'min_query_length' => 2,
                'placeholder_translations' => ['ru' => 'Поиск по сайту', 'en' => 'Search the site'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalizeForSave(array $input): array
    {
        $defaults = $this->defaults();
        $locales = $this->supportedLocales();

        $header = is_array($input['header'] ?? null) ? $input['header'] : [];
        $footer = is_array($input['footer'] ?? null) ? $input['footer'] : [];
        $search = is_array($input['search'] ?? null) ? $input['search'] : [];

        $searchPathSlug = $this->normalizeSlugSegment((string) ($search['path_slug'] ?? $defaults['search']['path_slug']));
        $reservedRouteSegments = array_values(array_filter(array_unique([
            trim((string) config('cms.post_url_prefix', 'blog'), '/'),
            trim((string) config('cms.category_url_prefix', 'category'), '/'),
            trim((string) config('cms.booking_url_prefix', 'book'), '/'),
        ])));
        if ($searchPathSlug === '' || in_array($searchPathSlug, $reservedRouteSegments, true)) {
            $searchPathSlug = (string) $defaults['search']['path_slug'];
        }

        return [
            'version' => 1,
            'header' => [
                'enabled' => $this->boolValue($header['enabled'] ?? $defaults['header']['enabled']),
                'variant' => $this->enumValue((string) ($header['variant'] ?? ''), ['split_nav', 'center_logo', 'stacked_compact'], (string) $defaults['header']['variant']),
                'show_brand_subtitle' => $this->boolValue($header['show_brand_subtitle'] ?? $defaults['header']['show_brand_subtitle']),
                'show_locale_switcher' => $this->boolValue($header['show_locale_switcher'] ?? $defaults['header']['show_locale_switcher']),
                'show_search' => $this->boolValue($header['show_search'] ?? $defaults['header']['show_search']),
                'search_placement' => $this->enumValue((string) ($header['search_placement'] ?? ''), ['header', 'footer', 'both', 'none'], (string) $defaults['header']['search_placement']),
                'nav_items' => $this->normalizeLinkItems($header['nav_items'] ?? $defaults['header']['nav_items'], 8, $locales),
                'cta_buttons' => $this->normalizeLinkItems($header['cta_buttons'] ?? $defaults['header']['cta_buttons'], 2, $locales, true),
            ],
            'footer' => [
                'enabled' => $this->boolValue($footer['enabled'] ?? $defaults['footer']['enabled']),
                'variant' => $this->enumValue((string) ($footer['variant'] ?? ''), ['inline', 'two_column', 'three_column'], (string) $defaults['footer']['variant']),
                'show_brand' => $this->boolValue($footer['show_brand'] ?? $defaults['footer']['show_brand']),
                'show_tagline' => $this->boolValue($footer['show_tagline'] ?? $defaults['footer']['show_tagline']),
                'tagline_translations' => $this->normalizeTranslationsMap($footer['tagline_translations'] ?? $defaults['footer']['tagline_translations'], $locales),
                'links' => $this->normalizeLinkItems($footer['links'] ?? $defaults['footer']['links'], 12, $locales),
                'social_links' => $this->normalizeLinkItems($footer['social_links'] ?? $defaults['footer']['social_links'], 6, $locales),
                'legal_links' => $this->normalizeLinkItems($footer['legal_links'] ?? $defaults['footer']['legal_links'], 6, $locales),
            ],
            'search' => [
                'enabled' => $this->boolValue($search['enabled'] ?? $defaults['search']['enabled']),
                'path_slug' => $searchPathSlug,
                'scope_default' => $this->enumValue((string) ($search['scope_default'] ?? ''), ['all', 'posts', 'pages'], (string) $defaults['search']['scope_default']),
                'results_per_page' => max(1, min(50, (int) ($search['results_per_page'] ?? $defaults['search']['results_per_page']))),
                'min_query_length' => max(1, min(20, (int) ($search['min_query_length'] ?? $defaults['search']['min_query_length']))),
                'placeholder_translations' => $this->normalizeTranslationsMap($search['placeholder_translations'] ?? $defaults['search']['placeholder_translations'], $locales),
            ],
        ];
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function enumValue(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function normalizeSlugSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = trim($value, '/');
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value;
    }

    /**
     * @param mixed $items
     * @param array<int, string> $locales
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLinkItems(mixed $items, int $max, array $locales, bool $withStyle = false): array
    {
        $items = is_array($items) ? array_values($items) : [];
        $normalized = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $labels = $this->normalizeTranslationsMap($item['label_translations'] ?? [], $locales);
            $url = trim((string) ($item['url'] ?? ''));
            $linkTarget = $this->normalizeLinkTarget($item['link_target'] ?? null);
            $hasAnyLabel = collect($labels)->contains(static fn (string $value): bool => trim($value) !== '');
            if ($url === '' && ! $hasAnyLabel && $linkTarget === null) {
                continue;
            }

            $row = [
                'id' => $this->normalizeItemId((string) ($item['id'] ?? ('item_'.($index + 1)))),
                'enabled' => $this->boolValue($item['enabled'] ?? true),
                'url' => mb_substr($url, 0, 2048),
                'new_tab' => $this->boolValue($item['new_tab'] ?? false),
                'nofollow' => $this->boolValue($item['nofollow'] ?? false),
                'label_translations' => $labels,
            ];

            if ($linkTarget !== null) {
                $row['link_target'] = $linkTarget;
            }

            if ($withStyle) {
                $row['style'] = $this->enumValue((string) ($item['style'] ?? ''), ['primary', 'secondary', 'ghost'], 'primary');
            }

            $normalized[] = $row;
            if (count($normalized) >= $max) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return array<string, int|string>|null
     */
    private function normalizeLinkTarget(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $type = strtolower(trim((string) ($value['type'] ?? '')));
        if (! in_array($type, ['page', 'post', 'category'], true)) {
            return null;
        }

        $id = (int) ($value['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * @param mixed $value
     * @param array<int, string> $locales
     * @return array<string, string>
     */
    private function normalizeTranslationsMap(mixed $value, array $locales): array
    {
        $source = is_array($value) ? $value : [];
        $out = [];

        foreach ($locales as $locale) {
            $out[$locale] = trim((string) ($source[$locale] ?? ''));
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocales(): array
    {
        $locales = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            config('cms.supported_locales', ['ru', 'en'])
        )));

        return $locales !== [] ? $locales : ['ru', 'en'];
    }

    private function normalizeItemId(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?? '';
        $value = preg_replace('/_+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'item';
    }
}
