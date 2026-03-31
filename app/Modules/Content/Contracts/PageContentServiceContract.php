<?php

namespace App\Modules\Content\Contracts;

use App\Models\ContentTemplate;
use App\Models\Page;
use App\Models\User;

interface PageContentServiceContract
{
    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $context
     */
    public function createFromValidated(array $validated, User $actor, array $context = []): Page;

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $context
     */
    public function updateFromValidated(Page $page, array $validated, User $actor, array $context = []): Page;

    /**
     * @param array<string, mixed> $context
     */
    public function duplicate(Page $page, User $actor, array $context = []): Page;

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
}
