<?php

namespace App\Modules\Core\Services;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;

class ChromeLinkTargetCatalogService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(): array
    {
        $locales = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            config('cms.supported_locales', ['ru', 'en'])
        )));
        if ($locales === []) {
            $locales = ['ru', 'en'];
        }

        $defaultLocale = strtolower((string) config('cms.default_locale', 'ru'));
        $blogPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');
        $categoryPrefix = trim((string) config('cms.category_url_prefix', 'category'), '/');

        $pickSlug = static function (array $slugs, array $orderedLocales) use ($defaultLocale): string {
            $preferred = trim((string) ($slugs[$defaultLocale] ?? ''));
            if ($preferred !== '') {
                return $preferred;
            }
            foreach ($orderedLocales as $locale) {
                $candidate = trim((string) ($slugs[$locale] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
            foreach ($slugs as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            return '';
        };

        $pickTitle = static function (array $titles, array $orderedLocales) use ($defaultLocale): string {
            $preferred = trim((string) ($titles[$defaultLocale] ?? ''));
            if ($preferred !== '') {
                return $preferred;
            }
            foreach ($orderedLocales as $locale) {
                $candidate = trim((string) ($titles[$locale] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
            foreach ($titles as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            return '';
        };

        $buildTitlesAndSlugs = static function ($translations) use ($locales): array {
            $titles = [];
            $slugs = [];
            foreach ($locales as $locale) {
                $titles[$locale] = '';
                $slugs[$locale] = '';
            }

            foreach ($translations as $translation) {
                $locale = strtolower((string) ($translation->locale ?? ''));
                if (! in_array($locale, $locales, true)) {
                    continue;
                }
                $titles[$locale] = trim((string) ($translation->title ?? ''));
                $slugs[$locale] = trim((string) ($translation->slug ?? ''));
            }

            return [$titles, $slugs];
        };

        $targets = [];

        $pages = Page::query()->with('translations')->latest('updated_at')->limit(200)->get();
        foreach ($pages as $page) {
            [$titles, $slugs] = $buildTitlesAndSlugs($page->translations);
            $slug = $pickSlug($slugs, $locales);
            if ($slug === '') {
                continue;
            }
            $title = $pickTitle($titles, $locales) ?: ('Page #'.$page->id);
            $pathTemplate = $slug === 'home' ? '/{locale}' : '/{locale}/'.$slug;
            $targets[] = [
                'key' => 'page:'.$page->id,
                'type' => 'page',
                'entity_id' => (int) $page->id,
                'status' => (string) ($page->status ?? ''),
                'label' => 'Страница · '.$title.' (#'.$page->id.')',
                'titles' => $titles,
                'slugs' => $slugs,
                'url_template' => $pathTemplate,
                'preview_path' => $pathTemplate,
            ];
        }

        $posts = Post::query()->with('translations')->latest('updated_at')->limit(200)->get();
        foreach ($posts as $post) {
            [$titles, $slugs] = $buildTitlesAndSlugs($post->translations);
            $slug = $pickSlug($slugs, $locales);
            if ($slug === '') {
                continue;
            }
            $title = $pickTitle($titles, $locales) ?: ('Post #'.$post->id);
            $pathTemplate = '/{locale}/'.$blogPrefix.'/'.$slug;
            $targets[] = [
                'key' => 'post:'.$post->id,
                'type' => 'post',
                'entity_id' => (int) $post->id,
                'status' => (string) ($post->status ?? ''),
                'label' => 'Пост · '.$title.' (#'.$post->id.')',
                'titles' => $titles,
                'slugs' => $slugs,
                'url_template' => $pathTemplate,
                'preview_path' => $pathTemplate,
            ];
        }

        $categories = Category::query()->with('translations')->latest('updated_at')->limit(200)->get();
        foreach ($categories as $category) {
            [$titles, $slugs] = $buildTitlesAndSlugs($category->translations);
            $slug = $pickSlug($slugs, $locales);
            if ($slug === '') {
                continue;
            }
            $title = $pickTitle($titles, $locales) ?: ('Category #'.$category->id);
            $pathTemplate = '/{locale}/'.$categoryPrefix.'/'.$slug;
            $targets[] = [
                'key' => 'category:'.$category->id,
                'type' => 'category',
                'entity_id' => (int) $category->id,
                'status' => (bool) ($category->is_active ?? false) ? 'active' : 'inactive',
                'label' => 'Категория · '.$title.' (#'.$category->id.')',
                'titles' => $titles,
                'slugs' => $slugs,
                'url_template' => $pathTemplate,
                'preview_path' => $pathTemplate,
            ];
        }

        usort($targets, static function (array $a, array $b): int {
            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return $targets;
    }
}
