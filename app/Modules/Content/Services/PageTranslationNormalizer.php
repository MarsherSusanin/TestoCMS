<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\Support\LocalizedContentHelpers;
use App\Modules\Content\Support\TranslationInputMappingHelpers;
use App\Modules\Core\Contracts\BlockRendererContract;
use Illuminate\Validation\ValidationException;

class PageTranslationNormalizer
{
    use LocalizedContentHelpers;
    use TranslationInputMappingHelpers;

    public function __construct(
        private readonly BlockSchemaValidator $blockSchemaValidator,
        private readonly PageLayoutNormalizer $pageLayoutNormalizer,
        private readonly BlockRendererContract $blockRenderer,
    ) {
    }

    /**
     * @param array<int|string, mixed> $translationsInput
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public function normalize(array $translationsInput, array $options = []): array
    {
        $inputByLocale = $this->translationsInputByLocale($translationsInput);
        $locales = $this->resolveLocalesForInput($translationsInput, $inputByLocale);
        $requireDefaultLocale = (bool) ($options['require_default_locale'] ?? $this->shouldRequireDefaultLocale($translationsInput));
        $ownerId = isset($options['owner_id']) ? (int) $options['owner_id'] : null;
        $assertUnique = (bool) ($options['assert_unique'] ?? true);
        $renderContext = is_array($options['render_context'] ?? null) ? $options['render_context'] : [];
        $normalized = [];

        foreach ($locales as $locale) {
            $item = $inputByLocale[$locale] ?? [];
            if (! is_array($item)) {
                $item = [];
            }

            $title = trim((string) ($item['title'] ?? ''));
            $slug = trim((string) ($item['slug'] ?? ''));
            if ($slug === '' && $title !== '') {
                $slug = $this->generateSlugFromTitle($title);
            }
            $slug = trim($slug, '/');

            $richHtml = (string) ($item['rich_html'] ?? '');
            $contentBlocks = null;
            if (array_key_exists('content_blocks', $item)) {
                $contentBlocks = is_array($item['content_blocks']) ? $item['content_blocks'] : [];
            } else {
                $contentBlocks = $this->decodeJsonArrayText((string) ($item['blocks_json'] ?? ''), "translations.{$locale}.blocks_json");
            }

            if ($contentBlocks === null) {
                $contentBlocks = trim($richHtml) !== ''
                    ? [['type' => 'rich_text', 'data' => ['html' => $richHtml]]]
                    : [];
            }

            $blocks = $this->pageLayoutNormalizer->normalize($contentBlocks, true);
            $metaTitle = $this->normalizeTextarea($item['meta_title'] ?? null);
            $metaDescription = $this->normalizeTextarea($item['meta_description'] ?? null);
            $canonicalUrl = $this->normalizeTextarea($item['canonical_url'] ?? null);
            if ($canonicalUrl === null && $slug !== '') {
                $canonicalUrl = $this->defaultCanonicalUrlForPage($locale, $slug);
            }
            $customHeadHtml = $this->normalizeTextarea($item['custom_head_html'] ?? null);
            $robotsDirectives = isset($item['robots_directives']) && is_array($item['robots_directives'])
                ? $item['robots_directives']
                : null;
            $structuredData = isset($item['structured_data']) && is_array($item['structured_data'])
                ? $item['structured_data']
                : null;

            $usageReasons = [];
            if ($title !== '') {
                $usageReasons[] = 'title';
            }
            if ($slug !== '') {
                $usageReasons[] = 'slug';
            }
            if (trim($richHtml) !== '') {
                $usageReasons[] = 'rich_html';
            }
            if ($this->hasMeaningfulBlocks($blocks)) {
                $usageReasons[] = 'content_blocks';
            }
            if ($metaTitle !== null) {
                $usageReasons[] = 'meta_title';
            }
            if ($metaDescription !== null) {
                $usageReasons[] = 'meta_description';
            }
            if ($canonicalUrl !== null) {
                $usageReasons[] = 'canonical_url';
            }
            if ($customHeadHtml !== null) {
                $usageReasons[] = 'custom_head_html';
            }
            if ($robotsDirectives !== null) {
                $usageReasons[] = 'robots_directives';
            }
            if ($structuredData !== null) {
                $usageReasons[] = 'structured_data';
            }

            if ($usageReasons === []) {
                continue;
            }

            if ($title === '' || $slug === '') {
                $reasonsList = implode(', ', $usageReasons);
                $message = sprintf(
                    'Locale %s contains content/settings (%s) but is missing title and/or slug.',
                    strtoupper($locale),
                    $reasonsList
                );

                throw ValidationException::withMessages([
                    "translations.{$locale}" => [$message],
                    "translations.{$locale}.title" => [$message],
                    "translations.{$locale}.slug" => [$message],
                ]);
            }

            $this->assertSlugAllowed($slug, "translations.{$locale}.slug", $locale);

            try {
                $this->blockSchemaValidator->validateOrFail($blocks);
            } catch (ValidationException $e) {
                $messages = $e->errors()['content_blocks'] ?? [$e->getMessage()];
                throw ValidationException::withMessages([
                    "translations.{$locale}.blocks_json" => array_values(array_map(
                        static fn (mixed $message): string => (string) $message,
                        (array) $messages
                    )),
                ]);
            }

            $normalized[$locale] = [
                'title' => $title,
                'slug' => $slug,
                'content_blocks' => $blocks,
                'rendered_html' => $this->blockRenderer->render($blocks, $renderContext + ['locale' => $locale]),
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'canonical_url' => $canonicalUrl,
                'custom_head_html' => $customHeadHtml,
                'robots_directives' => $robotsDirectives,
                'structured_data' => $structuredData,
            ];
        }

        if ($requireDefaultLocale) {
            $this->requireDefaultLocaleTranslation($normalized, ['title', 'slug']);
        }

        if ($assertUnique) {
            $this->assertUniqueTranslationSlugs($normalized, 'page_translations', 'page_id', $ownerId, 'page');
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $blocks
     */
    private function hasMeaningfulBlocks(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if ($this->hasMeaningfulNode($block)) {
                return true;
            }
        }

        return false;
    }

    private function hasMeaningfulNode(mixed $node): bool
    {
        if (! is_array($node)) {
            return false;
        }

        $type = trim((string) ($node['type'] ?? ''));
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        if ($type === 'section') {
            $children = $node['children'] ?? [];
            if (! is_array($children)) {
                return false;
            }

            foreach ($children as $child) {
                if ($this->hasMeaningfulNode($child)) {
                    return true;
                }
            }

            return false;
        }

        if ($type === 'columns') {
            $columns = $data['columns'] ?? [];
            if (! is_array($columns)) {
                return false;
            }

            foreach ($columns as $column) {
                if (! is_array($column)) {
                    continue;
                }
                $children = $column['children'] ?? [];
                if (! is_array($children)) {
                    continue;
                }
                foreach ($children as $child) {
                    if ($this->hasMeaningfulNode($child)) {
                        return true;
                    }
                }
            }

            return false;
        }

        if ($type === 'divider' || $type === 'post_listing') {
            return true;
        }

        if ($type === 'heading') {
            return trim((string) ($data['text'] ?? '')) !== '';
        }
        if ($type === 'rich_text') {
            return trim((string) ($data['html'] ?? '')) !== '';
        }
        if ($type === 'image') {
            return trim((string) ($data['src'] ?? '')) !== '';
        }
        if ($type === 'gallery') {
            return is_array($data['items'] ?? null) && count((array) $data['items']) > 0;
        }
        if ($type === 'list') {
            $items = $data['items'] ?? [];
            return is_array($items) && count(array_filter($items, static fn (mixed $value): bool => trim((string) $value) !== '')) > 0;
        }
        if ($type === 'cta') {
            return trim((string) ($data['label'] ?? '')) !== '' || trim((string) ($data['url'] ?? '')) !== '';
        }
        if ($type === 'table') {
            return is_array($data['rows'] ?? null) && count((array) $data['rows']) > 0;
        }
        if ($type === 'faq') {
            return is_array($data['items'] ?? null) && count((array) $data['items']) > 0;
        }
        if ($type === 'video_embed') {
            return trim((string) ($data['url'] ?? '')) !== '';
        }
        if ($type === 'module_widget') {
            return trim((string) ($data['module'] ?? '')) !== '' && trim((string) ($data['widget'] ?? '')) !== '';
        }
        if ($type === 'html_embed_restricted' || $type === 'custom_code_embed') {
            return trim((string) ($data['html'] ?? '')) !== '' || trim((string) ($data['label'] ?? '')) !== '';
        }

        return $type !== '';
    }
}
