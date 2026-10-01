<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CategoryTranslation;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class FeedController extends Controller
{
    public function blogFeed(string $locale): Response
    {
        $locale = strtolower($locale);
        abort_unless(in_array($locale, config('cms.supported_locales', ['en']), true), 404);
        $posts = Post::query()
            ->published()
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->renderFeed($posts, $locale, 'Blog feed');
    }

    public function categoryFeed(string $locale, string $slug): Response
    {
        $locale = strtolower($locale);
        abort_unless(in_array($locale, config('cms.supported_locales', ['en']), true), 404);

        $categoryTranslation = CategoryTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->firstOrFail();

        $posts = Post::query()
            ->published()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryTranslation->category_id))
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->renderFeed($posts, $locale, 'Category: '.$categoryTranslation->title);
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private function renderFeed($posts, string $locale, string $title): Response
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $rss = $document->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $document->appendChild($rss);
        $channel = $document->createElement('channel');
        $rss->appendChild($channel);
        $append = static function (\DOMElement $parent, string $name, string $value) use ($document): void {
            $element = $document->createElement($name);
            // XML 1.0 forbids these controls, even inside CDATA.
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
            $element->appendChild($document->createTextNode($value));
            $parent->appendChild($element);
        };
        $append($channel, 'title', $title);
        $append($channel, 'link', url('/'.$locale));
        $append($channel, 'description', (string) config('seo.site.description'));
        $postPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');
        foreach ($posts as $post) {
            $translation = $post->translations->first();
            if ($translation === null) {
                continue;
            }
            $item = $document->createElement('item');
            $channel->appendChild($item);
            $url = url('/'.$locale.'/'.$postPrefix.'/'.$translation->slug);
            $append($item, 'title', (string) $translation->title);
            $append($item, 'link', $url);
            $append($item, 'guid', $url);
            $append($item, 'pubDate', $post->published_at?->toRssString() ?? now()->toRssString());
            $append($item, 'description', (string) $translation->getAttribute('excerpt'));
        }

        return response($document->saveXML() ?: '', 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
        ]);
    }
}
