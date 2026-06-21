<?php

namespace App\Modules\Content\Services;

use App\Models\Page;
use App\Models\PageTranslation;

class PageTranslationPersisterService
{
    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    public function upsert(Page $page, array $translations): void
    {
        foreach ($translations as $locale => $item) {
            PageTranslation::query()->updateOrCreate(
                [
                    'page_id' => $page->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'content_blocks' => $item['content_blocks'],
                    'rendered_html' => $item['rendered_html'],
                    'meta_title' => $item['meta_title'],
                    'meta_description' => $item['meta_description'],
                    'canonical_url' => $item['canonical_url'],
                    'custom_head_html' => $item['custom_head_html'],
                    'robots_directives' => $item['robots_directives'],
                    'structured_data' => $item['structured_data'],
                ]
            );
        }

        // Prune locales that are no longer part of the submitted set so a
        // removed translation stops resolving publicly (and its slug frees up).
        $keep = array_keys($translations);
        if ($keep !== []) {
            $page->translations()->whereNotIn('locale', $keep)->get()
                ->each(static fn (PageTranslation $translation) => $translation->delete());
        }
    }
}
