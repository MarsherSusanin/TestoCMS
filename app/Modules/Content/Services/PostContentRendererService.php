<?php

namespace App\Modules\Content\Services;

use App\Modules\Core\Contracts\SanitizerContract;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class PostContentRendererService
{
    private ?MarkdownConverter $markdownConverter = null;

    public function __construct(
        private readonly SanitizerContract $sanitizer,
    ) {
    }

    /**
     * @return array{content_html:string,content_plain:string}
     */
    public function sanitizeHtmlContent(string $html): array
    {
        return $this->sanitizeRichContent($html);
    }

    /**
     * @return array{content_markdown:string,content_html:string,content_plain:string,front_matter:array<string,string|null>}
     */
    public function renderMarkdownContent(string $markdown, bool $withFrontMatter = false): array
    {
        $normalized = $this->normalizeLineEndings($markdown);
        $frontMatter = [];
        $body = $normalized;

        if ($withFrontMatter) {
            ['front_matter' => $frontMatter, 'body' => $body] = $this->extractFrontMatter($normalized);
        }

        $html = (string) $this->markdownConverter()->convert($body);
        $rendered = $this->sanitizeRichContent($html);

        return [
            'content_markdown' => trim($body),
            'content_html' => $rendered['content_html'],
            'content_plain' => $rendered['content_plain'],
            'front_matter' => $frontMatter,
        ];
    }

    /**
     * @return array{
     *   title:?string,
     *   slug:?string,
     *   excerpt:?string,
     *   meta_title:?string,
     *   meta_description:?string,
     *   canonical_url:?string,
     *   custom_head_html:?string,
     *   content_markdown:string,
     *   content_html:string,
     *   content_plain:string
     * }
     */
    public function importMarkdownDocument(string $markdown): array
    {
        $rendered = $this->renderMarkdownContent($markdown, true);
        $frontMatter = $rendered['front_matter'];

        return [
            'title' => $frontMatter['title'] ?? null,
            'slug' => $frontMatter['slug'] ?? null,
            'excerpt' => $frontMatter['excerpt'] ?? null,
            'meta_title' => $frontMatter['meta_title'] ?? null,
            'meta_description' => $frontMatter['meta_description'] ?? null,
            'canonical_url' => $frontMatter['canonical_url'] ?? null,
            'custom_head_html' => $frontMatter['custom_head_html'] ?? null,
            'content_markdown' => $rendered['content_markdown'],
            'content_html' => $rendered['content_html'],
            'content_plain' => $rendered['content_plain'],
        ];
    }

    private function markdownConverter(): MarkdownConverter
    {
        if ($this->markdownConverter instanceof MarkdownConverter) {
            return $this->markdownConverter;
        }

        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        return $this->markdownConverter = new MarkdownConverter($environment);
    }

    /**
     * @return array{front_matter:array<string,string|null>,body:string}
     */
    private function extractFrontMatter(string $markdown): array
    {
        if (! str_starts_with($markdown, "---\n")) {
            return [
                'front_matter' => [],
                'body' => $markdown,
            ];
        }

        $closingMarkerPos = strpos($markdown, "\n---\n", 4);
        if ($closingMarkerPos === false) {
            return [
                'front_matter' => [],
                'body' => $markdown,
            ];
        }

        $yaml = substr($markdown, 4, $closingMarkerPos - 4);
        $body = substr($markdown, $closingMarkerPos + 5);

        try {
            $parsed = Yaml::parse($yaml);
        } catch (ParseException) {
            return [
                'front_matter' => [],
                'body' => $markdown,
            ];
        }

        if (! is_array($parsed)) {
            $parsed = [];
        }

        $frontMatter = [];
        foreach (['title', 'slug', 'excerpt', 'meta_title', 'meta_description', 'canonical_url', 'custom_head_html'] as $field) {
            $value = $parsed[$field] ?? null;
            if ($value === null || is_array($value) || is_object($value)) {
                $frontMatter[$field] = null;
                continue;
            }

            $text = trim((string) $value);
            $frontMatter[$field] = $text === '' ? null : $text;
        }

        return [
            'front_matter' => $frontMatter,
            'body' => ltrim($body, "\n"),
        ];
    }

    /**
     * @return array{content_html:string,content_plain:string}
     */
    private function sanitizeRichContent(string $html): array
    {
        $safeScripts = array_merge(
            $this->extractSafeExternalScripts($html),
            $this->extractSafeInlineModuleScripts($html),
        );

        $withoutScripts = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        if (! is_string($withoutScripts)) {
            $withoutScripts = $html;
        }

        $sanitizedBase = $this->sanitizer->sanitizeHtml($withoutScripts, 'restricted_embed');
        $finalHtml = $safeScripts === []
            ? $sanitizedBase
            : trim($sanitizedBase."\n".implode("\n", $safeScripts));

        return [
            'content_html' => $finalHtml,
            'content_plain' => trim(strip_tags($sanitizedBase)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractSafeExternalScripts(string $html): array
    {
        $scripts = [];
        $result = preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $matches, PREG_SET_ORDER);
        if (! is_int($result) || $result < 1) {
            return $scripts;
        }

        foreach ($matches as $match) {
            $attrs = (string) ($match[1] ?? '');
            $body = trim((string) ($match[2] ?? ''));
            if ($body !== '') {
                continue;
            }

            if (! preg_match('/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $attrs, $srcMatch)) {
                continue;
            }

            $src = trim((string) ($srcMatch[2] ?? ''));
            if (! $this->isAllowedEmbedSourceUrl($src)) {
                continue;
            }

            $isModule = preg_match('/\btype\s*=\s*([\'"]?)module\1/i', $attrs) === 1;
            $flags = [];
            if (preg_match('/\basync\b/i', $attrs) === 1) {
                $flags[] = 'async';
            }
            if (preg_match('/\bdefer\b/i', $attrs) === 1) {
                $flags[] = 'defer';
            }
            $typeAttr = $isModule ? ' type="module"' : '';

            $scripts[] = '<script'.$typeAttr.' src="'.e($src).'"'.($flags !== [] ? ' '.implode(' ', $flags) : '').'></script>';
        }

        return $scripts;
    }

    /**
     * @return array<int, string>
     */
    private function extractSafeInlineModuleScripts(string $html): array
    {
        $scripts = [];
        $result = preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $matches, PREG_SET_ORDER);
        if (! is_int($result) || $result < 1) {
            return $scripts;
        }

        foreach ($matches as $match) {
            $attrs = (string) ($match[1] ?? '');
            if (preg_match('/\btype\s*=\s*([\'"]?)module\1/i', $attrs) !== 1) {
                continue;
            }
            if (preg_match('/\bsrc\s*=\s*([\'"])([^\'"]+)\1/i', $attrs) === 1) {
                continue;
            }

            $body = trim((string) ($match[2] ?? ''));
            if ($body === '' || strlen($body) > 40000) {
                continue;
            }
            if (! $this->hasOnlyAllowedModuleImports($body)) {
                continue;
            }

            $scripts[] = "<script type=\"module\">\n{$body}\n</script>";
        }

        return $scripts;
    }

    private function hasOnlyAllowedModuleImports(string $body): bool
    {
        $imports = [];

        $staticResult = preg_match_all(
            '/\bimport\s+[\s\S]*?\s+from\s+([\'"])([^\'"]+)\1\s*;?/i',
            $body,
            $staticMatches
        );
        if (is_int($staticResult) && $staticResult > 0) {
            foreach (($staticMatches[2] ?? []) as $specifier) {
                $imports[] = trim((string) $specifier);
            }
        }

        $dynamicResult = preg_match_all(
            '/\bimport\s*\(\s*([\'"])([^\'"]+)\1\s*\)/i',
            $body,
            $dynamicMatches
        );
        if (is_int($dynamicResult) && $dynamicResult > 0) {
            foreach (($dynamicMatches[2] ?? []) as $specifier) {
                $imports[] = trim((string) $specifier);
            }
        }

        $imports = array_values(array_unique(array_filter($imports, static fn (string $item): bool => $item !== '')));
        if ($imports === []) {
            return false;
        }

        foreach ($imports as $importUrl) {
            if (! $this->isAllowedEmbedSourceUrl($importUrl)) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedEmbedSourceUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $domains = config('cms.custom_code.safe_embed_domains', []);
        foreach ($domains as $domain) {
            $allowed = strtolower(trim((string) $domain));
            if ($allowed === '') {
                continue;
            }
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
