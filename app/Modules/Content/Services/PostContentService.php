<?php

namespace App\Modules\Content\Services;

use App\Models\ContentTemplate;
use App\Models\Post;
use App\Models\User;
use App\Modules\Content\Contracts\PostContentServiceContract;
use App\Modules\Content\Support\LocalizedContentHelpers;
use Illuminate\Support\Facades\DB;

class PostContentService implements PostContentServiceContract
{
    use LocalizedContentHelpers;

    public function __construct(
        private readonly PostContentRendererService $contentRenderer,
        private readonly PostTranslationNormalizer $translationNormalizer,
        private readonly PostTemplatePayloadBuilder $templatePayloadBuilder,
        private readonly SlugUniquenessService $slugUniqueness,
        private readonly PostTranslationPersisterService $translationPersister,
        private readonly ContentMutationFinalizerService $mutationFinalizer,
    ) {}

    public function createFromValidated(array $validated, User $actor, array $context = []): Post
    {
        $translations = $this->normalizeTranslations($validated['translations'] ?? [], [
            'require_default_locale' => $context['require_default_locale'] ?? $this->shouldRequireDefaultLocale($validated['translations'] ?? []),
            'owner_id' => null,
            'assert_unique' => true,
        ]);

        $post = DB::transaction(function () use ($validated, $translations, $actor): Post {
            $post = Post::query()->create([
                'author_id' => $actor->id,
                'featured_asset_id' => $validated['featured_asset_id'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'published_at' => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
            ]);

            $this->translationPersister->upsert($post, $translations);
            $post->categories()->sync($validated['category_ids'] ?? []);

            return $post;
        });

        return $this->mutationFinalizer->finalize($post, 'post', ['translations', 'categories'], $actor, $context + [
            'audit_action' => $this->resolveAuditAction('create'),
            'audit_context' => ['status' => $post->status],
        ]);
    }

    public function updateFromValidated(Post $post, array $validated, User $actor, array $context = []): Post
    {
        $translations = $this->normalizeTranslations($validated['translations'] ?? [], [
            'require_default_locale' => $context['require_default_locale'] ?? $this->shouldRequireDefaultLocale($validated['translations'] ?? []),
            'owner_id' => (int) $post->id,
            'assert_unique' => true,
        ]);

        DB::transaction(function () use ($post, $validated, $translations): void {
            $post->fill([
                'featured_asset_id' => array_key_exists('featured_asset_id', $validated)
                    ? ($validated['featured_asset_id'] ?? null)
                    : $post->featured_asset_id,
                'status' => $validated['status'] ?? $post->status,
            ]);

            if ($post->status === 'published' && $post->published_at === null) {
                $post->published_at = now();
            }

            $post->save();
            $this->translationPersister->upsert($post, $translations);

            if (array_key_exists('category_ids', $validated)) {
                $post->categories()->sync($validated['category_ids'] ?? []);
            }
        });

        return $this->mutationFinalizer->finalize($post, 'post', ['translations', 'categories'], $actor, $context + [
            'audit_action' => $this->resolveAuditAction('update'),
            'audit_context' => ['status' => $post->status],
        ]);
    }

    public function duplicate(Post $post, User $actor, array $context = []): Post
    {
        $post->loadMissing(['translations', 'categories']);

        $validated = [
            'featured_asset_id' => $post->featured_asset_id,
            'status' => 'draft',
            'category_ids' => $post->categories->pluck('id')->all(),
            'translations' => [],
        ];

        foreach ($post->translations as $translation) {
            $seed = $this->slugUniqueness->duplicateSeed((string) $translation->slug);
            $slug = $this->slugUniqueness->uniquePostSlug((string) $translation->locale, $seed);

            $validated['translations'][] = [
                'locale' => strtolower((string) $translation->locale),
                'title' => (string) $translation->title,
                'slug' => $slug,
                'content_format' => (string) ($translation->content_format ?? 'html'),
                'content_html' => (string) ($translation->content_html ?? ''),
                'content_markdown' => (string) ($translation->content_markdown ?? ''),
                'excerpt' => $translation->excerpt,
                'meta_title' => $translation->meta_title,
                'meta_description' => $translation->meta_description,
                'canonical_url' => $this->defaultCanonicalUrlForPost((string) $translation->locale, $slug),
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

    public function previewMarkdown(string $markdown): array
    {
        $rendered = $this->contentRenderer->renderMarkdownContent($markdown);

        return [
            'content_html' => (string) ($rendered['content_html'] ?? ''),
            'content_plain' => (string) ($rendered['content_plain'] ?? ''),
        ];
    }

    public function importMarkdownDocument(string $markdown, string $locale): array
    {
        $locale = strtolower(trim($locale));
        $imported = $this->contentRenderer->importMarkdownDocument($markdown);
        $title = trim((string) ($imported['title'] ?? ''));
        $slug = trim((string) ($imported['slug'] ?? ''));
        if ($slug === '' && $title !== '') {
            $slug = $this->generateSlugFromTitle($title);
        }

        return [
            'locale' => $locale,
            'title' => $title !== '' ? $title : null,
            'slug' => $slug !== '' ? $slug : null,
            'excerpt' => $imported['excerpt'] ?? null,
            'meta_title' => $imported['meta_title'] ?? null,
            'meta_description' => $imported['meta_description'] ?? null,
            'canonical_url' => $imported['canonical_url'] ?? ($slug !== '' ? $this->defaultCanonicalUrlForPost($locale, $slug) : null),
            'custom_head_html' => $imported['custom_head_html'] ?? null,
            'content_format' => 'markdown',
            'content_markdown' => $imported['content_markdown'],
            'content_html' => $imported['content_html'],
            'content_plain' => $imported['content_plain'],
        ];
    }

    private function resolveAuditAction(string $operation): string
    {
        return request()?->is('api/*') ? 'post.'.$operation : 'post.'.$operation.'.web';
    }
}
