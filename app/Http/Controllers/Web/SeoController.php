<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CategoryTranslation;
use App\Models\PageTranslation;
use App\Models\PostTranslation;
use App\Models\SeoSetting;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeoController extends Controller
{
    public function robotsTxt(): Response
    {
        $settings = SeoSetting::global();

        if (! empty($settings->robots_txt_custom)) {
            return response($settings->robots_txt_custom."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Sitemap: '.url('/sitemap-index.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemapIndex(): Response
    {
        $items = [];
        foreach (config('cms.supported_locales', ['en']) as $locale) {
            $items[] = [
                'loc' => url('/sitemaps/'.strtolower((string) $locale).'.xml'),
                'lastmod' => now()->toAtomString(),
            ];
        }

        $entries = [];
        foreach ($items as $item) {
            $entries[] = sprintf(
                '<sitemap><loc>%s</loc><lastmod>%s</lastmod></sitemap>',
                e($item['loc']),
                e($item['lastmod']),
            );
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', $entries)
            .'</sitemapindex>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function sitemapLocale(string $locale): StreamedResponse
    {
        $locale = strtolower($locale);
        if (! in_array($locale, config('cms.supported_locales', ['en']), true)) {
            abort(404);
        }

        $postPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');
        $categoryPrefix = trim((string) config('cms.category_url_prefix', 'category'), '/');

        return response()->stream(function () use ($locale, $postPrefix, $categoryPrefix) {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            // Stream Posts
            $postsCursor = PostTranslation::query()
                ->where('locale', $locale)
                ->whereHas('post', fn ($q) => $q->where('status', 'published')->whereNotNull('published_at'))
                ->with('post')
                ->cursor();

            foreach ($postsCursor as $translation) {
                $this->echoSitemapUrl(
                    url('/'.$locale.'/'.$postPrefix.'/'.$translation->slug),
                    $translation->updated_at?->toAtomString()
                );
            }

            // Stream Pages
            $pagesCursor = PageTranslation::query()
                ->where('locale', $locale)
                ->whereHas('page', fn ($q) => $q->where('status', 'published')->whereNotNull('published_at'))
                ->cursor();

            foreach ($pagesCursor as $translation) {
                $this->echoSitemapUrl(
                    url('/'.$locale.'/'.$translation->slug),
                    $translation->updated_at?->toAtomString()
                );
            }

            // Stream Categories
            $categoriesCursor = CategoryTranslation::query()
                ->where('locale', $locale)
                ->whereHas('category', fn ($q) => $q->where('is_active', true))
                ->cursor();

            foreach ($categoriesCursor as $translation) {
                $this->echoSitemapUrl(
                    url('/'.$locale.'/'.$categoryPrefix.'/'.$translation->slug),
                    $translation->updated_at?->toAtomString()
                );
            }

            echo '</urlset>';
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llmsTxt(): StreamedResponse
    {
        $locale = config('cms.default_locale', 'en');
        $postPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');

        return response()->stream(function () use ($locale, $postPrefix) {
            $settings = SeoSetting::global();

            if (! empty($settings->llms_txt_intro)) {
                echo $settings->llms_txt_intro."\n\n";
            } else {
                echo "# ".config('app.name')." LLM Overview\n\n";
            }

            echo "## Recent Posts\n\n";

            // Stream the latest 100 posts for AI context
            $postsCursor = PostTranslation::query()
                ->where('locale', $locale)
                ->whereHas('post', fn ($q) => $q->where('status', 'published')->whereNotNull('published_at'))
                ->with('post')
                ->latest('id')
                ->take(100)
                ->cursor();

            foreach ($postsCursor as $translation) {
                $url = url('/'.$locale.'/'.$postPrefix.'/'.$translation->slug);
                $title = str_replace(["\r", "\n"], ' ', (string) $translation->title);
                $desc = str_replace(["\r", "\n"], ' ', (string) $translation->meta_description);
                echo sprintf("- [%s](%s)", $title, $url);
                if (! empty($desc)) {
                    echo ": ".$desc;
                }
                echo "\n";
            }

            echo "\n## Core Pages\n\n";

            $pagesCursor = PageTranslation::query()
                ->where('locale', $locale)
                ->whereHas('page', fn ($q) => $q->where('status', 'published')->whereNotNull('published_at'))
                ->take(50)
                ->cursor();

            foreach ($pagesCursor as $translation) {
                $url = url('/'.$locale.'/'.$translation->slug);
                $title = str_replace(["\r", "\n"], ' ', (string) $translation->title);
                $desc = str_replace(["\r", "\n"], ' ', (string) $translation->meta_description);
                echo sprintf("- [%s](%s)", $title, $url);
                if (! empty($desc)) {
                    echo ": ".$desc;
                }
                echo "\n";
            }
        }, 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    private function echoSitemapUrl(string $loc, ?string $lastmod): void
    {
        $lastmod = $lastmod ?? now()->toAtomString();
        echo sprintf(
            '  <url><loc>%s</loc><lastmod>%s</lastmod></url>'."\n",
            e($loc),
            e($lastmod)
        );
    }
}
