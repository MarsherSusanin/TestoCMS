<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CategoryTranslation;
use App\Models\Post;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    public function blogFeed(string $locale): Response
    {
        $locale = strtolower($locale);
        $posts = Post::query()
            ->published()
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return $this->renderFeed($posts, $locale, 'Blog feed');
    }

    public function categoryFeed(string $locale, string $slug): Response
    {
        $locale = strtolower($locale);

        $categoryTranslation = CategoryTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Post::query()
            ->published()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryTranslation->category_id))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return $this->renderFeed($posts, $locale, 'Category: '.$categoryTranslation->title);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Post>  $posts
     */
    private function renderFeed($posts, string $locale, string $title): Response
    {
        $items = [];
        $postPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');

        foreach ($posts as $post) {
            $translation = $post->translations->first();
            if ($translation === null) {
                continue;
            }

            $url = url('/'.$locale.'/'.$postPrefix.'/'.$translation->slug);
            $items[] = sprintf(
                '<item><title>%s</title><link>%s</link><guid>%s</guid><pubDate>%s</pubDate><description><![CDATA[%s]]></description></item>',
                e($translation->title),
                e($url),
                e($url),
                $post->published_at?->toRssString() ?? now()->toRssString(),
                $translation->excerpt ?? '',
            );
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel>'
            .'<title>'.e($title).'</title>'
            .'<link>'.e(url('/'.$locale)).'</link>'
            .'<description>'.e(config('seo.site.description')).'</description>'
            .implode('', $items)
            .'</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
