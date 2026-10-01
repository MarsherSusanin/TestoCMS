<?php

namespace App\Modules\Content\Services;

use App\Models\Asset;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\ContentTemplate;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Resolves live references, including URLs pasted without an asset ID. */
class AssetUsageService
{
    /** @return list<array<string, mixed>> */
    public function usages(Asset $asset): array
    {
        $urls = [$asset->public_url];
        try {
            $urls[] = Storage::disk($asset->disk)->url($asset->storage_path);
        } catch (\Throwable) {
        }
        // Shared owners can expose different URLs for the same physical object.
        foreach (Asset::query()->where('disk', $asset->disk)->where('storage_path', $asset->storage_path)->get() as $owner) {
            $urls[] = $owner->public_url;
        }
        $targets = array_values(array_unique(array_filter(array_map(fn ($url) => is_string($url) ? $this->canonicalUrl($url) : '', $urls))));
        $usages = [];
        foreach (Post::query()->where('featured_asset_id', $asset->id)->get(['id']) as $post) {
            $usages[] = $this->usage('post', $post->id, null, 'featured_asset_id');
        }
        foreach (Category::query()->where('cover_asset_id', $asset->id)->get(['id']) as $category) {
            $usages[] = $this->usage('category', $category->id, null, 'cover_asset_id');
        }
        $sources = [
            [PageTranslation::class, 'page', 'page_id', ['content_blocks', 'rendered_html', 'custom_head_html', 'structured_data']],
            [PostTranslation::class, 'post', 'post_id', ['content_html', 'content_markdown', 'custom_head_html', 'structured_data']],
            [CategoryTranslation::class, 'category', 'category_id', ['description', 'structured_data']],
            [Page::class, 'page', 'id', ['custom_code']],
            [ContentTemplate::class, 'template', 'id', ['payload']],
            [ThemeSetting::class, 'theme', 'id', ['settings']],
        ];
        foreach ($sources as [$model, $type, $ownerKey, $fields]) {
            $model::query()->chunkById(100, function ($records) use (&$usages, $targets, $type, $ownerKey, $fields, $asset): void {
                foreach ($records as $record) {
                    foreach ($fields as $field) {
                        if ($this->references($record->getAttribute($field), $targets, $asset->id)) {
                            $usages[] = $this->usage($type, (int) $record->getAttribute($ownerKey), $record->getAttribute('locale'), $field);
                        }
                    }
                }
            });
        }

        return $usages;
    }

    /** @return array<string, mixed> */
    private function usage(string $type, int $id, ?string $locale, string $field): array
    {
        $url = match ($type) {
            'template' => '/admin/templates', 'theme' => '/admin/theme',
            default => '/admin/'.($type === 'category' ? 'categories' : $type.'s').'/'.$id.'/edit',
        };

        return ['entity_type' => $type, 'id' => $id, 'locale' => $locale, 'field' => $field, 'admin_url' => $url];
    }

    /** @param list<string> $targets */
    private function references(mixed $value, array $targets, int $assetId): bool
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (in_array($key, ['asset_id', 'featured_asset_id', 'cover_asset_id'], true) && (string) $item === (string) $assetId) {
                    return true;
                }
                if ($this->references($item, $targets, $assetId)) {
                    return true;
                }
            }

            return false;
        }
        if (! is_string($value)) {
            return false;
        }
        foreach ($this->mediaUrls($value) as $url) {
            if (in_array($this->canonicalUrl($url), $targets, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function mediaUrls(string $value): array
    {
        $urls = [];
        // A JSON URL field is represented by the entire value, never arbitrary prose.
        $whole = trim($value);
        if (preg_match('~^(?:https?://|//|/)[^\s<>"\']+$~u', $whole)) {
            $urls[] = $whole;
        }
        preg_match_all('~\b(?:src|href|poster|data-src)\s*=\s*(["\'])(.*?)\1~is', $value, $html);
        foreach ($html[2] as $url) {
            $urls[] = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
        }
        preg_match_all('~\b(?:src|href|poster|data-src)\s*=\s*([^\s<>"\']+)~is', $value, $unquoted);
        foreach ($unquoted[1] as $url) {
            $urls[] = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
        }
        preg_match_all('~\bsrcset\s*=\s*(["\'])(.*?)\1~is', $value, $srcsets);
        foreach ($srcsets[2] as $srcset) {
            foreach (explode(',', $srcset) as $candidate) {
                $urls[] = preg_split('/\s+/', trim($candidate))[0];
            }
        }
        preg_match_all('~url\(\s*(["\']?)(.*?)\1\s*\)~is', $value, $css);
        foreach ($css[2] as $url) {
            $urls[] = $url;
        }
        preg_match_all('~!?\[[^\]]*\]\(\s*<?([^\s)>]+)>?(?:\s+["\'][^)]*)?\)~u', $value, $markdown);
        foreach ($markdown[1] as $url) {
            $urls[] = $url;
        }
        preg_match_all('~^\s*\[[^\]]+\]:\s*<?([^\s>]+)>?~m', $value, $definitions);
        foreach ($definitions[1] as $url) {
            $urls[] = $url;
        }
        // Code uses quoted URL literals, so detect complete literals rather than substrings.
        preg_match_all('~(["\'`])((?:https?://|/)[^"\'`\s<>]+)\1~u', $value, $code);
        foreach ($code[2] as $url) {
            $urls[] = $url;
        }

        return $urls;
    }

    public function canonicalUrl(string $url): string
    {
        $parts = parse_url(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5));
        if (! is_array($parts)) {
            return '';
        }
        $path = $parts['path'] ?? '';
        $host = strtolower($parts['host'] ?? '');
        $app = parse_url((string) config('app.url', ''));
        $appHost = is_array($app) ? strtolower($app['host'] ?? '') : '';
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'https') === 'http' ? 80 : 443);
        $appPort = is_array($app) ? ($app['port'] ?? (($app['scheme'] ?? 'https') === 'http' ? 80 : 443)) : 443;
        if (($host === $appHost && $port === $appPort) || $host === '') {
            $host = '';
        }

        return ($host === '' ? '' : $host.':'.($parts['port'] ?? (($parts['scheme'] ?? 'https') === 'http' ? 80 : 443))).rawurldecode($path);
    }

    /** Check inside the media mutex before persisting newly submitted references. */
    public function assertReferencesAvailable(mixed $payload): void
    {
        $urls = [];
        $this->collectReferences($payload, $urls);
        if ($urls === []) {
            return;
        }
        foreach (app(AssetDeletionJournal::class)->entries() as $entry) {
            foreach ($entry['urls'] ?? [] as $target) {
                if (in_array($this->canonicalUrl($target), $urls, true) && ! (($entry['state'] ?? '') === 'deleted' && Storage::disk($entry['disk'])->exists($entry['path']))) {
                    throw ValidationException::withMessages(['media' => ['A referenced media file was deleted or is being deleted. Remove or replace its reference.']]);
                }
            }
        }
    }

    /** @param list<string> $urls */
    private function collectReferences(mixed $payload, array &$urls): void
    {
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                if (in_array($key, ['asset_id', 'featured_asset_id', 'cover_asset_id'], true) && $value !== null && $value !== '' && (! is_scalar($value) || ! Asset::query()->whereKey($value)->exists())) {
                    throw ValidationException::withMessages([(string) $key => ['Asset no longer exists.']]);
                }
                $this->collectReferences($value, $urls);
            }
        } elseif (is_string($payload)) {
            foreach ($this->mediaUrls($payload) as $url) {
                $urls[] = $this->canonicalUrl($url);
            }
        }
    }
}
