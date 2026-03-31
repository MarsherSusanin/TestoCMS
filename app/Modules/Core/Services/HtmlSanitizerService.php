<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Contracts\SanitizerContract;

class HtmlSanitizerService implements SanitizerContract
{
    /**
     * @var array<string, \HTMLPurifier>
     */
    private array $purifiers = [];

    public function sanitizeHtml(string $html, string $profile = 'default'): string
    {
        $purifier = $this->purifiers[$profile] ??= $this->buildPurifier($profile);

        return trim($purifier->purify($html));
    }

    private function buildPurifier(string $profile): \HTMLPurifier
    {
        $cachePath = storage_path('app/purifier');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('Attr.EnableID', $profile === 'restricted_embed');
        $config->set('HTML.DefinitionID', 'testocms-'.$profile);
        $config->set('HTML.DefinitionRev', 3);

        $allowed = [
            'p[class]',
            'a[class|href|title|target|rel]',
            'ul[class]',
            'ol[class]',
            'li[class]',
            'strong',
            'em',
            'blockquote[class]',
            'code[class]',
            'pre[class]',
            'h1[class]',
            'h2[class]',
            'h3[class]',
            'h4[class]',
            'h5[class]',
            'h6[class]',
            'img[class|src|alt|title|width|height|loading]',
            'figure[class]',
            'figcaption[class]',
            'table[class]',
            'thead[class]',
            'tbody[class]',
            'tr[class]',
            'th[class]',
            'td[class]',
            'hr[class]',
            'br',
            'span[class]',
            'div[id|class]',
            'section[id|class]',
            'article[class]',
            'details[class]',
            'summary[class]',
        ];

        if ($profile === 'restricted_embed') {
            $domains = config('cms.custom_code.safe_embed_domains', []);
            $quoted = implode('|', array_map(static fn (string $domain): string => preg_quote($domain, '#'), $domains));

            $allowed[] = 'iframe[id|src|width|height|allowfullscreen|frameborder|title|loading]';
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp', '#^https?://([a-z0-9-]+\\.)*('.$quoted.')/.*$#i');
        }

        if ($profile === 'advanced' || $profile === 'raw') {
            $allowed[] = 'style';
        }

        $config->set('HTML.Allowed', implode(',', $allowed));
        $this->extendHtmlDefinition($config);

        return new \HTMLPurifier($config);
    }

    private function extendHtmlDefinition(\HTMLPurifier_Config $config): void
    {
        $def = $config->maybeGetRawHTMLDefinition();
        if (! $def) {
            return;
        }

        // HTMLPurifier default definition is HTML4-ish. Register HTML5 structural tags
        // that our editors insert (media picker/gallery/FAQ/section templates).
        $def->addElement('figure', 'Block', 'Flow', 'Common');
        $def->addElement('figcaption', 'Block', 'Inline', 'Common');
        $def->addElement('section', 'Block', 'Flow', 'Common');
        $def->addElement('article', 'Block', 'Flow', 'Common');
        $def->addElement('details', 'Block', 'Flow', 'Common');
        $def->addElement('summary', 'Block', 'Inline', 'Common');

        // Preserve modern non-dangerous attrs used by our generated HTML.
        $def->addAttribute('img', 'loading', 'Text');
        $def->addAttribute('iframe', 'loading', 'Text');
        $def->addAttribute('iframe', 'allowfullscreen', 'Text');
        $def->addAttribute('details', 'open', 'Text');
    }
}
