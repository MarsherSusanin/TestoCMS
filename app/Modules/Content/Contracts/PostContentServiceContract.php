<?php

namespace App\Modules\Content\Contracts;

use App\Models\ContentTemplate;
use App\Models\Post;
use App\Models\User;

interface PostContentServiceContract
{
    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $context
     */
    public function createFromValidated(array $validated, User $actor, array $context = []): Post;

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $context
     */
    public function updateFromValidated(Post $post, array $validated, User $actor, array $context = []): Post;

    /**
     * @param array<string, mixed> $context
     */
    public function duplicate(Post $post, User $actor, array $context = []): Post;

    /**
     * @param array<int|string, mixed> $translationsInput
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public function normalizeTranslations(array $translationsInput, array $options = []): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function normalizeTemplatePayload(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function buildTemplatePrefill(ContentTemplate $template): array;

    /**
     * @return array{content_html:string,content_plain:string}
     */
    public function previewMarkdown(string $markdown): array;

    /**
     * @return array<string, mixed>
     */
    public function importMarkdownDocument(string $markdown, string $locale): array;
}
