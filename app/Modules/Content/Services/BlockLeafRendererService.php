<?php

namespace App\Modules\Content\Services;

use App\Models\PostTranslation;
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
            'carousel' => $this->renderCarousel($data),
            'list' => $this->renderList($data),
            'divider' => '<hr class="cms-divider" />',
            'cta' => $this->renderCta($data),
            'table' => $this->renderTable($data),
            'module_widget' => $this->renderModuleWidget($data, $context),
            'custom_code_embed' => $this->renderCustomCodeEmbed($data),
            'html_embed_restricted' => $this->renderRestrictedHtml($data),
            'post_listing' => $this->renderPostListing($data, $context),
            'faq' => $this->renderFaq($data),
            'stats' => $this->renderStats($data),
            'hero' => $this->renderHero($data),
            'features' => $this->renderFeatures($data),
            'testimonial' => $this->renderTestimonial($data),
            'pricing' => $this->renderPricing($data),
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
        $dimensions = $this->imageDimensionAttrs($data);

        return "<figure><img src=\"{$src}\" alt=\"{$alt}\"{$dimensions} loading=\"lazy\" decoding=\"async\" />{$captionHtml}</figure>";
    }

    /**
     * Emit intrinsic width/height attributes when known so the browser can
     * reserve space and avoid layout shift (CLS).
     *
     * @param  array<string, mixed>  $data
     */
    private function imageDimensionAttrs(array $data): string
    {
        $width = (int) ($data['width'] ?? 0);
        $height = (int) ($data['height'] ?? 0);

        return ($width > 0 && $height > 0) ? " width=\"{$width}\" height=\"{$height}\"" : '';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderVideo(array $data): string
    {
        $url = trim((string) ($data['url'] ?? ''));
        if ($url === '' || ! $this->isAllowedEmbedUrl($url)) {
            return '';
        }

        return '<div class="cms-video"><iframe src="'.e($url).'" title="'.e((string) ($data['title'] ?? 'Embedded video')).'" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe></div>';
    }

    /**
     * Only allow https iframe sources whose host is on the configured
     * safe-embed allowlist, so the video block cannot embed an arbitrary
     * (phishing / clickjacking / javascript:) origin.
     */
    private function isAllowedEmbedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        foreach ((array) config('cms.custom_code.safe_embed_domains', []) as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
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
            $dimensions = $this->imageDimensionAttrs($item);
            $images[] = "<img src=\"{$src}\" alt=\"{$alt}\"{$dimensions} loading=\"lazy\" decoding=\"async\" />";
        }

        return '<div class="cms-gallery">'.implode('', $images).'</div>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderCarousel(array $data): string
    {
        $height = (string) ($data['height'] ?? 'lg');
        if (! in_array($height, ['md', 'lg', 'xl'], true)) {
            $height = 'lg';
        }

        $align = (string) ($data['overlay_align'] ?? 'left');
        if (! in_array($align, ['left', 'center', 'right'], true)) {
            $align = 'left';
        }

        $theme = (string) ($data['overlay_theme'] ?? 'gradient');
        if (! in_array($theme, ['gradient', 'dark', 'light'], true)) {
            $theme = 'gradient';
        }

        $autoplay = (bool) ($data['autoplay'] ?? false);
        $interval = max(1500, min(30000, (int) ($data['interval_ms'] ?? 5000)));
        $showArrows = ($data['show_arrows'] ?? true) !== false;
        $showDots = ($data['show_dots'] ?? true) !== false;
        $slides = is_array($data['slides'] ?? null) ? $data['slides'] : [];

        $renderedSlides = [];
        $dotButtons = [];

        foreach ($slides as $index => $slide) {
            if (! is_array($slide)) {
                continue;
            }

            $src = trim((string) ($slide['src'] ?? ''));
            if ($src === '') {
                continue;
            }

            $title = trim((string) ($slide['title'] ?? ''));
            $text = trim((string) ($slide['text'] ?? ''));
            $ctaLabel = trim((string) ($slide['cta_label'] ?? ''));
            $ctaUrl = trim((string) ($slide['cta_url'] ?? ''));
            $targetBlank = (bool) ($slide['target_blank'] ?? false);
            $nofollow = (bool) ($slide['nofollow'] ?? false);

            $ctaHtml = '';
            if ($ctaLabel !== '') {
                $relParts = [];
                if ($targetBlank) {
                    $relParts[] = 'noopener';
                    $relParts[] = 'noreferrer';
                }
                if ($nofollow) {
                    $relParts[] = 'nofollow';
                }
                $targetAttr = $targetBlank ? ' target="_blank"' : '';
                $relAttr = $relParts !== [] ? ' rel="'.e(implode(' ', array_values(array_unique($relParts)))).'"' : '';
                $ctaHtml = '<a class="cms-cta" href="'.e($this->safeLinkUrl($ctaUrl)).'"'.$targetAttr.$relAttr.'>'.e($ctaLabel).'</a>';
            }

            $overlayParts = array_filter([
                $title !== '' ? '<h3 class="cms-carousel-title">'.e($title).'</h3>' : '',
                $text !== '' ? '<p class="cms-carousel-text">'.e($text).'</p>' : '',
                $ctaHtml,
            ]);
            $overlayHtml = $overlayParts !== []
                ? '<div class="cms-carousel-overlay"><div class="cms-carousel-copy">'.implode('', $overlayParts).'</div></div>'
                : '';

            $renderedSlides[] = '<article class="cms-carousel-slide'.($renderedSlides === [] ? ' is-active' : '').'" data-cms-carousel-slide>'
                .'<img src="'.e($src).'" alt="'.e((string) ($slide['alt'] ?? '')).'" loading="lazy" />'
                .$overlayHtml
                .'</article>';
            $dotButtons[] = '<button type="button" class="cms-carousel-dot'.($renderedSlides !== [] && count($renderedSlides) === 1 ? ' is-active' : '').'" data-cms-carousel-dot="'.count($dotButtons).'" aria-label="'.e($title !== '' ? $title : 'Слайд '.(count($dotButtons) + 1)).'"></button>';
        }

        if ($renderedSlides === []) {
            return '';
        }

        $arrowsHtml = $showArrows && count($renderedSlides) > 1
            ? '<div class="cms-carousel-arrows"><button type="button" class="cms-carousel-arrow prev" data-cms-carousel-prev aria-label="Предыдущий слайд">‹</button><button type="button" class="cms-carousel-arrow next" data-cms-carousel-next aria-label="Следующий слайд">›</button></div>'
            : '';
        $dotsHtml = $showDots && count($renderedSlides) > 1
            ? '<div class="cms-carousel-dots" data-cms-carousel-dots>'.implode('', $dotButtons).'</div>'
            : '';

        return '<div class="cms-carousel cms-carousel--height-'.e($height).' cms-carousel--align-'.e($align).' cms-carousel--theme-'.e($theme).'" data-cms-carousel data-autoplay="'.($autoplay ? 'true' : 'false').'" data-interval-ms="'.e((string) $interval).'"><div class="cms-carousel-track">'.implode('', $renderedSlides).'</div>'.$arrowsHtml.$dotsHtml.'</div>';
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
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     */
    private function renderPostListing(array $data, array $context = []): string
    {
        $categorySlug = trim((string) ($data['category_slug'] ?? ''));
        $limit = max(1, min(100, (int) ($data['limit'] ?? 10)));
        $locale = strtolower((string) ($context['locale'] ?? config('cms.default_locale', 'en')));
        $postPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');

        $items = PostTranslation::query()
            ->where('locale', $locale)
            ->whereHas('post', function ($query) use ($categorySlug, $locale): void {
                $query->published();
                if ($categorySlug !== '') {
                    $query->whereHas('categories.translations', function ($categoryQuery) use ($categorySlug, $locale): void {
                        $categoryQuery->where('locale', $locale)->where('slug', $categorySlug);
                    });
                }
            })
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'post_id', 'locale', 'slug', 'title', 'excerpt', 'meta_description']);

        $attrs = 'data-category="'.e($categorySlug).'" data-limit="'.$limit.'"';

        if ($items->isEmpty()) {
            return '<div class="cms-post-listing" '.$attrs.'></div>';
        }

        $html = '<ul class="cms-post-listing" '.$attrs.'>';
        foreach ($items as $translation) {
            $url = e(url('/'.$locale.'/'.$postPrefix.'/'.$translation->slug));
            $title = e((string) $translation->title);
            $excerpt = trim((string) ($translation->excerpt ?: $translation->meta_description ?: ''));

            $html .= '<li class="cms-post-listing__item"><a class="cms-post-listing__link" href="'.$url.'">'.$title.'</a>';
            if ($excerpt !== '') {
                $html .= '<p class="cms-post-listing__excerpt">'.e($excerpt).'</p>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
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

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderStats(array $data): string
    {
        $items = $data['items'] ?? [];
        if (! is_array($items)) {
            return '';
        }

        $cards = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $value = trim((string) ($item['value'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($value === '' && $label === '') {
                continue;
            }

            $cards[] = '<div class="cms-stat">'
                .'<span class="cms-stat-value">'.e($value).'</span>'
                .'<span class="cms-stat-label">'.e($label).'</span>'
                .'</div>';
        }

        if ($cards === []) {
            return '';
        }

        return '<div class="cms-stats">'.implode('', $cards).'</div>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderHero(array $data): string
    {
        $heading = trim((string) ($data['heading'] ?? ''));
        $subheading = trim((string) ($data['subheading'] ?? ''));
        if ($heading === '' && $subheading === '') {
            return '';
        }

        $align = (string) ($data['align'] ?? 'left');
        $align = in_array($align, ['left', 'center'], true) ? $align : 'left';
        $image = trim((string) ($data['image'] ?? ''));
        $style = ($image !== '' && (str_starts_with($image, '/') || str_starts_with($image, 'https://')))
            ? ' style="background-image:url(\''.e($image).'\')"'
            : '';

        $ctaLabel = trim((string) ($data['cta_label'] ?? ''));
        $cta = $ctaLabel !== ''
            ? '<a class="cms-cta" href="'.e($this->safeLinkUrl((string) ($data['cta_url'] ?? '#'))).'">'.e($ctaLabel).'</a>'
            : '';

        return '<section class="cms-hero cms-hero--align-'.e($align).'"'.$style.'><div class="cms-hero-copy">'
            .($heading !== '' ? '<h1 class="cms-hero-title">'.e($heading).'</h1>' : '')
            .($subheading !== '' ? '<p class="cms-hero-sub">'.e($subheading).'</p>' : '')
            .$cta
            .'</div></section>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderFeatures(array $data): string
    {
        $cards = [];
        foreach ((is_array($data['items'] ?? null) ? $data['items'] : []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));
            $icon = trim((string) ($item['icon'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            $cards[] = '<div class="cms-feature">'
                .($icon !== '' ? '<div class="cms-feature-icon">'.e($icon).'</div>' : '')
                .'<h3 class="cms-feature-title">'.e($title).'</h3>'
                .'<p class="cms-feature-text">'.e($text).'</p></div>';
        }

        return $cards === [] ? '' : '<div class="cms-features">'.implode('', $cards).'</div>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderTestimonial(array $data): string
    {
        $cards = [];
        foreach ((is_array($data['items'] ?? null) ? $data['items'] : []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $quote = trim((string) ($item['quote'] ?? ''));
            if ($quote === '') {
                continue;
            }
            $author = trim((string) ($item['author'] ?? ''));
            $role = trim((string) ($item['role'] ?? ''));
            $cards[] = '<figure class="cms-testimonial"><blockquote>'.e($quote).'</blockquote><figcaption>'
                .'<span class="cms-testimonial-author">'.e($author).'</span>'
                .($role !== '' ? '<span class="cms-testimonial-role">'.e($role).'</span>' : '')
                .'</figcaption></figure>';
        }

        return $cards === [] ? '' : '<div class="cms-testimonials">'.implode('', $cards).'</div>';
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function renderPricing(array $data): string
    {
        $cards = [];
        foreach ((is_array($data['items'] ?? null) ? $data['items'] : []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            $price = trim((string) ($item['price'] ?? ''));
            if ($name === '' && $price === '') {
                continue;
            }
            $period = trim((string) ($item['period'] ?? ''));
            $features = '';
            foreach ((is_array($item['features'] ?? null) ? $item['features'] : []) as $feature) {
                $feature = trim((string) $feature);
                if ($feature !== '') {
                    $features .= '<li>'.e($feature).'</li>';
                }
            }
            $ctaLabel = trim((string) ($item['cta_label'] ?? ''));
            $cta = $ctaLabel !== ''
                ? '<a class="cms-cta" href="'.e($this->safeLinkUrl((string) ($item['cta_url'] ?? '#'))).'">'.e($ctaLabel).'</a>'
                : '';

            $cards[] = '<div class="cms-price"><h3 class="cms-price-name">'.e($name).'</h3>'
                .'<div class="cms-price-amount">'.e($price).($period !== '' ? '<span class="cms-price-period">/'.e($period).'</span>' : '').'</div>'
                .'<ul class="cms-price-features">'.$features.'</ul>'.$cta.'</div>';
        }

        return $cards === [] ? '' : '<div class="cms-pricing">'.implode('', $cards).'</div>';
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
