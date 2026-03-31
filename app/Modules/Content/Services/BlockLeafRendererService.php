<?php

namespace App\Modules\Content\Services;

use App\Modules\Core\Contracts\SanitizerContract;
use App\Modules\Extensibility\Registry\ModuleWidgetRegistry;

class BlockLeafRendererService
{
    public function __construct(
        private readonly SanitizerContract $sanitizer,
        private readonly ModuleWidgetRegistry $moduleWidgets,
    ) {}

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<string, mixed>  $context
     */
    public function render(string $type, array $data, array $context = []): string
    {
        return match ($type) {
            'heading' => $this->renderHeading($data),
            'rich_text' => $this->renderRichText($data),
            'image' => $this->renderImage($data),
            'video_embed' => $this->renderVideo($data),
            'gallery' => $this->renderGallery($data),
            'list' => $this->renderList($data),
            'divider' => '<hr class="cms-divider" />',
            'cta' => $this->renderCta($data),
            'table' => $this->renderTable($data),
            'module_widget' => $this->renderModuleWidget($data, $context),
            'custom_code_embed' => $this->renderCustomCodeEmbed($data),
            'html_embed_restricted' => $this->renderRestrictedHtml($data),
            'post_listing' => $this->renderPostListing($data),
            'faq' => $this->renderFaq($data),
            default => '',
        };
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderHeading(array $data): string
    {
        $level = (int) ($data['level'] ?? 2);
        $level = max(1, min(6, $level));
        $text = e((string) ($data['text'] ?? ''));

        return "<h{$level}>{$text}</h{$level}>";
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderRichText(array $data): string
    {
        return $this->sanitizer->sanitizeHtml((string) ($data['html'] ?? ''));
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderImage(array $data): string
    {
        $src = e((string) ($data['src'] ?? ''));
        $alt = e((string) ($data['alt'] ?? ''));
        $caption = (string) ($data['caption'] ?? '');
        $captionHtml = $caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '';

        return "<figure><img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" />{$captionHtml}</figure>";
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderVideo(array $data): string
    {
        $url = (string) ($data['url'] ?? '');
        if ($url === '') {
            return '';
        }

        return '<div class="cms-video"><iframe src="'.e($url).'" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe></div>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderGallery(array $data): string
    {
        $items = $data['items'] ?? [];
        if (! is_array($items)) {
            return '';
        }

        $images = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $src = e((string) ($item['src'] ?? ''));
            $alt = e((string) ($item['alt'] ?? ''));
            $images[] = "<img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" />";
        }

        return '<div class="cms-gallery">'.implode('', $images).'</div>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderList(array $data): string
    {
        $ordered = (bool) ($data['ordered'] ?? false);
        $tag = $ordered ? 'ol' : 'ul';
        $items = $data['items'] ?? [];
        if (! is_array($items)) {
            return '';
        }

        $lis = [];
        foreach ($items as $item) {
            $lis[] = '<li>'.e((string) $item).'</li>';
        }

        return "<{$tag}>".implode('', $lis)."</{$tag}>";
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderCta(array $data): string
    {
        $label = e((string) ($data['label'] ?? 'Learn more'));
        $url = e($this->safeLinkUrl((string) ($data['url'] ?? '#')));
        $targetBlank = (bool) ($data['target_blank'] ?? false);
        $nofollow = (bool) ($data['nofollow'] ?? false);
        $style = trim((string) ($data['style'] ?? ''));
        $styleClass = in_array($style, ['primary', 'secondary', 'ghost'], true) ? ' is-'.$style : '';
        $targetAttr = $targetBlank ? ' target="_blank"' : '';
        $relParts = [];
        if ($targetBlank) {
            $relParts[] = 'noopener';
            $relParts[] = 'noreferrer';
        }
        if ($nofollow) {
            $relParts[] = 'nofollow';
        }
        $relAttr = $relParts !== [] ? ' rel="'.e(implode(' ', array_values(array_unique($relParts)))).'"' : '';

        return "<p><a class=\"cms-cta{$styleClass}\" href=\"{$url}\"{$targetAttr}{$relAttr}>{$label}</a></p>";
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderTable(array $data): string
    {
        $rows = $data['rows'] ?? [];
        if (! is_array($rows)) {
            return '';
        }

        $body = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = [];
            foreach ($row as $cell) {
                $cells[] = '<td>'.e((string) $cell).'</td>';
            }
            $body[] = '<tr>'.implode('', $cells).'</tr>';
        }

        return '<table><tbody>'.implode('', $body).'</tbody></table>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderRestrictedHtml(array $data): string
    {
        return $this->sanitizeRestrictedEmbedHtml((string) ($data['html'] ?? ''), false, false);
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderCustomCodeEmbed(array $data): string
    {
        $html = (string) ($data['html'] ?? '');
        $sanitized = $this->sanitizeRestrictedEmbedHtml($html, true, true);
        if ($sanitized === '') {
            return '';
        }

        $label = trim((string) ($data['label'] ?? ''));
        $labelHtml = $label !== '' ? '<div class="cms-embed-label">'.e($label).'</div>' : '';

        return '<div class="cms-custom-embed">'.$labelHtml.$sanitized.'</div>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<string, mixed>  $context
     */
    private function renderModuleWidget(array $data, array $context): string
    {
        $module = strtolower(trim((string) ($data['module'] ?? '')));
        $widget = trim((string) ($data['widget'] ?? ''));
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        if ($module === '' || $widget === '') {
            return '';
        }

        return $this->moduleWidgets->render($module, $widget, $config, $context);
    }

    private function sanitizeRestrictedEmbedHtml(string $html, bool $allowExternalScripts, bool $allowInlineModuleScripts): string
    {
        $safeScripts = $allowExternalScripts ? $this->extractSafeExternalScripts($html) : [];
        if ($allowExternalScripts && $allowInlineModuleScripts) {
            $safeScripts = array_merge($safeScripts, $this->extractSafeInlineModuleScripts($html));
        }
        $withoutScripts = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        if (! is_string($withoutScripts)) {
            $withoutScripts = $html;
        }

        $sanitized = $this->sanitizer->sanitizeHtml($withoutScripts, 'restricted_embed');
        if ($safeScripts === []) {
            return $sanitized;
        }

        return trim($sanitized."\n".implode("\n", $safeScripts));
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

        $staticResult = preg_match_all('/\bimport\s+[\s\S]*?\s+from\s+([\'"])([^\'"]+)\1\s*;?/i', $body, $staticMatches);
        if (is_int($staticResult) && $staticResult > 0) {
            foreach (($staticMatches[2] ?? []) as $specifier) {
                $imports[] = trim((string) $specifier);
            }
        }

        $dynamicResult = preg_match_all('/\bimport\s*\(\s*([\'"])([^\'"]+)\1\s*\)/i', $body, $dynamicMatches);
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

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderPostListing(array $data): string
    {
        $category = e((string) ($data['category_slug'] ?? ''));
        $limit = max(1, min(100, (int) ($data['limit'] ?? 10)));

        return "<div class=\"cms-post-listing\" data-category=\"{$category}\" data-limit=\"{$limit}\"></div>";
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderFaq(array $data): string
    {
        $items = $data['items'] ?? [];
        if (! is_array($items)) {
            return '';
        }

        $output = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = e((string) ($item['question'] ?? ''));
            $answer = $this->sanitizer->sanitizeHtml((string) ($item['answer'] ?? ''));
            $output[] = "<details><summary>{$question}</summary><div>{$answer}</div></details>";
        }

        return '<section class="cms-faq">'.implode('', $output).'</section>';
    }

    private function safeLinkUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return $url;
        }

        if (preg_match('/^(https?:|mailto:|tel:)/i', $url) === 1) {
            return $url;
        }

        return '#';
    }
}
