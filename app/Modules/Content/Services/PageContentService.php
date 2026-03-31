<?php

namespace App\Modules\Content\Services;

use App\Models\ContentTemplate;
use App\Models\Page;
use App\Models\User;
use App\Modules\Content\Contracts\PageContentServiceContract;
use App\Modules\Content\Support\LocalizedContentHelpers;
use Illuminate\Support\Facades\DB;

class PageContentService implements PageContentServiceContract
{
    use LocalizedContentHelpers;

    public function __construct(
        private readonly PageTranslationNormalizer $translationNormalizer,
        private readonly PageTemplatePayloadBuilder $templatePayloadBuilder,
        private readonly PageCustomCodePolicy $customCodePolicy,
        private readonly PageLayoutNormalizer $pageLayoutNormalizer,
        private readonly SlugUniquenessService $slugUniqueness,
        private readonly PageTranslationPersisterService $translationPersister,
        private readonly ContentMutationFinalizerService $mutationFinalizer,
    ) {}

    public function createFromValidated(array $validated, User $actor, array $context = []): Page
    {
        $translations = $this->normalizeTranslations($validated['translations'] ?? [], [
            'require_default_locale' => $context['require_default_locale'] ?? $this->shouldRequireDefaultLocale($validated['translations'] ?? []),
            'owner_id' => null,
            'assert_unique' => true,
        ]);

        $page = DB::transaction(function () use ($validated, $translations, $actor): Page {
            $sanitizeCustomCode = ! array_key_exists('sanitize_custom_code', $validated)
                || $this->boolFromMixed($validated['sanitize_custom_code']);

            $page = Page::query()->create([
                'author_id' => $actor->id,
                'status' => $validated['status'] ?? 'draft',
                'page_type' => $validated['page_type'] ?? 'landing',
                'custom_code' => array_key_exists('custom_code', $validated)
                    ? ($sanitizeCustomCode
                        ? $this->customCodePolicy->prepare($validated['custom_code'], $actor)
                        : $validated['custom_code'])
                    : null,
                'published_at' => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
            ]);

            $this->translationPersister->upsert($page, $translations);

            return $page;
        });

        return $this->mutationFinalizer->finalize($page, 'page', ['translations'], $actor, $context + [
            'audit_action' => $this->resolveAuditAction('create'),
            'audit_context' => ['status' => $page->status],
        ]);
    }

    public function updateFromValidated(Page $page, array $validated, User $actor, array $context = []): Page
    {
        $translations = $this->normalizeTranslations($validated['translations'] ?? [], [
            'require_default_locale' => $context['require_default_locale'] ?? $this->shouldRequireDefaultLocale($validated['translations'] ?? []),
            'owner_id' => (int) $page->id,
            'assert_unique' => true,
        ]);

        DB::transaction(function () use ($page, $validated, $translations, $actor): void {
            $sanitizeCustomCode = ! array_key_exists('sanitize_custom_code', $validated)
                || $this->boolFromMixed($validated['sanitize_custom_code']);

            $page->fill([
                'status' => $validated['status'] ?? $page->status,
                'page_type' => $validated['page_type'] ?? $page->page_type,
            ]);

            if (array_key_exists('custom_code', $validated)) {
                $page->custom_code = $sanitizeCustomCode
                    ? $this->customCodePolicy->prepare($validated['custom_code'], $actor)
                    : $validated['custom_code'];
            }

            if ($page->status === 'published' && $page->published_at === null) {
                $page->published_at = now();
            }

            $page->save();
            $this->translationPersister->upsert($page, $translations);
        });

        return $this->mutationFinalizer->finalize($page, 'page', ['translations'], $actor, $context + [
            'audit_action' => $this->resolveAuditAction('update'),
            'audit_context' => ['status' => $page->status],
        ]);
    }

    public function duplicate(Page $page, User $actor, array $context = []): Page
    {
        $page->loadMissing('translations');

        $validated = [
            'status' => 'draft',
            'page_type' => $page->page_type,
            'custom_code' => $page->custom_code,
            'sanitize_custom_code' => false,
            'translations' => [],
        ];

        foreach ($page->translations as $translation) {
            $seed = $this->slugUniqueness->duplicateSeed((string) $translation->slug);
            $slug = $this->slugUniqueness->uniquePageSlug((string) $translation->locale, $seed);
            $validated['translations'][] = [
                'locale' => strtolower((string) $translation->locale),
                'title' => (string) $translation->title,
                'slug' => $slug,
                'content_blocks' => $this->pageLayoutNormalizer->normalize($translation->content_blocks, true),
                'meta_title' => $translation->meta_title,
                'meta_description' => $translation->meta_description,
                'canonical_url' => $this->defaultCanonicalUrlForPage((string) $translation->locale, $slug),
                'custom_head_html' => $translation->custom_head_html,
                'robots_directives' => is_array($translation->robots_directives) ? $translation->robots_directives : null,
                'structured_data' => is_array($translation->structured_data) ? $translation->structured_data : null,
            ];
        }

        return $this->createFromValidated($validated, $actor, $context + [
            'require_default_locale' => false,
            'audit_action' => $context['audit_action'] ?? null,
        ]);
    }

    public function normalizeTranslations(array $translationsInput, array $options = []): array
    {
        return $this->translationNormalizer->normalize($translationsInput, $options);
    }

    public function normalizeTemplatePayload(array $payload): array
    {
        return $this->templatePayloadBuilder->normalizePayload($payload);
    }

    public function buildTemplatePrefill(ContentTemplate $template): array
    {
        return $this->templatePayloadBuilder->buildPrefill($template);
    }

    /**
     * @param  array<string, mixed>|null  $customCode
     * @return array<string, mixed>|null
     */
    public function prepareCustomCode(?array $customCode, ?User $actor): ?array
    {
        return $this->customCodePolicy->prepare($customCode, $actor);
    }

    private function resolveAuditAction(string $operation): string
    {
        return request()?->is('api/*') ? 'page.'.$operation : 'page.'.$operation.'.web';
    }
}
