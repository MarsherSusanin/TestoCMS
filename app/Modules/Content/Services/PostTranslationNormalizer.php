<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\Support\LocalizedContentHelpers;
use App\Modules\Content\Support\TranslationInputMappingHelpers;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostTranslationNormalizer
{
    use LocalizedContentHelpers;
    use TranslationInputMappingHelpers;

    public function __construct(private readonly PostContentRendererService $contentRenderer) {}

    /**
     * @param  array<int|string, mixed>  $translationsInput
     * @param  array<string, mixed>  $options
     * @return array<string, array<string, mixed>>
     */
    public function normalize(array $translationsInput, array $options = []): array
    {
        $inputByLocale = $this->translationsInputByLocale($translationsInput);
        $locales = $this->resolveLocalesForInput($translationsInput, $inputByLocale);
        $requireDefaultLocale = (bool) ($options['require_default_locale'] ?? $this->shouldRequireDefaultLocale($translationsInput));
        $ownerId = isset($options['owner_id']) ? (int) $options['owner_id'] : null;
        $assertUnique = (bool) ($options['assert_unique'] ?? true);
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

            $contentFormat = strtolower(trim((string) ($item['content_format'] ?? 'html')));
            if (! in_array($contentFormat, ['html', 'markdown'], true)) {
                $contentFormat = 'html';
            }

            $contentHtml = (string) ($item['content_html'] ?? '');
            $contentMarkdown = (string) ($item['content_markdown'] ?? '');
            $excerpt = $this->normalizeTextarea($item['excerpt'] ?? null);
            $metaTitle = $this->normalizeTextarea($item['meta_title'] ?? null);
            $metaDescription = $this->normalizeTextarea($item['meta_description'] ?? null);
            $canonicalUrl = $this->normalizeTextarea($item['canonical_url'] ?? null);
            if ($canonicalUrl === null && $slug !== '') {
                $canonicalUrl = $this->defaultCanonicalUrlForPost($locale, $slug);
            }
            $customHeadHtml = $this->normalizeTextarea($item['custom_head_html'] ?? null);
            $robotsDirectives = isset($item['robots_directives']) && is_array($item['robots_directives'])
                ? $item['robots_directives']
                : null;
            $structuredData = isset($item['structured_data']) && is_array($item['structured_data'])
                ? $item['structured_data']
                : null;

            $rendered = $contentFormat === 'markdown'
                ? $this->contentRenderer->renderMarkdownContent($contentMarkdown)
                : $this->contentRenderer->sanitizeHtmlContent($contentHtml);

            $normalizedMarkdown = $contentFormat === 'markdown'
                ? (string) ($rendered['content_markdown'] ?? trim($contentMarkdown))
                : null;
            $effectiveContentHtml = (string) ($rendered['content_html'] ?? '');
            $effectiveContentPlain = (string) ($rendered['content_plain'] ?? '');

            $usageReasons = [];
            if ($title !== '') {
                $usageReasons[] = 'title';
            }
            if ($slug !== '') {
                $usageReasons[] = 'slug';
            }
            if ($contentFormat === 'markdown' && trim((string) $normalizedMarkdown) !== '') {
                $usageReasons[] = 'content_markdown';
            }
            if ($contentFormat === 'html' && trim($contentHtml) !== '') {
                $usageReasons[] = 'content_html';
            }
            if ($excerpt !== null) {
                $usageReasons[] = 'excerpt';
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

            $normalized[$locale] = [
                'title' => $title,
                'slug' => $slug,
                'content_format' => $contentFormat,
                'content_html' => $effectiveContentHtml,
                'content_markdown' => $normalizedMarkdown,
                'content_plain' => $effectiveContentPlain,
                'excerpt' => $excerpt ?? (Str::limit($effectiveContentPlain, 180) ?: null),
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
            $this->assertUniqueTranslationSlugs($normalized, 'post_translations', 'post_id', $ownerId, 'post');
        }

        return $normalized;
    }
}
