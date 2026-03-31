<?php

namespace App\Modules\Content\Services;

use App\Models\Post;
use App\Models\PostTranslation;

class PostTranslationPersisterService
{
    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    public function upsert(Post $post, array $translations): void
    {
        foreach ($translations as $locale => $item) {
            PostTranslation::query()->updateOrCreate(
                [
                    'post_id' => $post->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'content_format' => $item['content_format'],
                    'content_html' => $item['content_html'],
                    'content_markdown' => $item['content_markdown'],
                    'content_plain' => $item['content_plain'],
                    'excerpt' => $item['excerpt'],
                    'meta_title' => $item['meta_title'],
                    'meta_description' => $item['meta_description'],
                    'canonical_url' => $item['canonical_url'],
                    'custom_head_html' => $item['custom_head_html'],
                    'robots_directives' => $item['robots_directives'],
                    'structured_data' => $item['structured_data'],
                ]
            );
        }
    }
}
