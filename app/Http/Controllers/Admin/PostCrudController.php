<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithLocalizedAdminForms;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\ContentTemplate;
use App\Models\Post;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Modules\Content\Contracts\PostContentServiceContract;
use App\Modules\Content\Contracts\PostWorkflowServiceContract;
use App\Modules\Content\Services\ContentBulkActionService;
use App\Modules\Content\Services\ContentTemplateService;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostCrudController extends Controller
{
    use InteractsWithLocalizedAdminForms;

    public function __construct(
        private readonly ContentBulkActionService $bulkActions,
        private readonly ContentTemplateService $templates,
        private readonly PostContentServiceContract $posts,
        private readonly PostWorkflowServiceContract $postWorkflow,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Post::class);
        $perPage = $this->resolvePerPage($request, 'admin.posts.per_page');

        $posts = Post::query()
            ->with(['translations', 'categories.translations'])
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'locales' => $this->supportedLocales(),
            'perPage' => $perPage,
            'perPageOptions' => [10, 20, 50, 100],
            'templates' => ContentTemplate::query()
                ->where('entity_type', ContentTemplateService::ENTITY_POST)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['id', 'name', 'description']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Post::class);
        $template = $this->resolveTemplate($request);
        $prefill = $template
            ? $this->templates->buildPrefillPayload($template)
            : ['entity' => [], 'translations' => []];
        $prefillTranslations = $this->toTranslationObjects($prefill['translations'] ?? []);
        $prefillCategoryIds = array_values(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            (array) ($prefill['entity']['category_ids'] ?? [])
        ), static fn (int $id): bool => $id > 0));
        $featuredAssetId = (int) ($prefill['entity']['featured_asset_id'] ?? 0);

        return view('admin.posts.form', [
            'post' => new Post([
                'status' => 'draft',
                'featured_asset_id' => $featuredAssetId > 0 ? $featuredAssetId : null,
            ]),
            'translationsByLocale' => $prefillTranslations,
            'locales' => $this->supportedLocales(),
            'isEdit' => false,
            'categories' => Category::query()->with('translations')->orderByDesc('id')->get(),
            'assets' => $this->assetOptionsWithSelected($featuredAssetId > 0 ? $featuredAssetId : null),
            'selectedCategoryIds' => $prefillCategoryIds,
            'schedules' => collect(),
            'previewTokens' => collect(),
            'templates' => ContentTemplate::query()
                ->where('entity_type', ContentTemplateService::ENTITY_POST)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['id', 'name', 'description']),
            'templateSource' => $template,
        ]);
    }

    public function previewMarkdown(Request $request): JsonResponse
    {
        $this->ensureCanWritePosts($request);

        $validated = $request->validate([
            'markdown' => ['nullable', 'string'],
        ]);

        $rendered = $this->posts->previewMarkdown((string) ($validated['markdown'] ?? ''));

        return response()->json([
            'data' => [
                'html' => $rendered['content_html'],
                'plain' => $rendered['content_plain'],
            ],
        ]);
    }

    public function importMarkdown(Request $request): JsonResponse
    {
        $this->ensureCanWritePosts($request);

        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:8'],
            'markdown_file' => ['required', 'file', 'mimetypes:text/plain,text/markdown,text/x-markdown,application/octet-stream', 'mimes:md,markdown,txt', 'max:2048'],
        ]);

        $locale = strtolower(trim((string) $validated['locale']));
        abort_unless(in_array($locale, $this->supportedLocales(), true), 422);

        return response()->json([
            'data' => $this->posts->importMarkdownDocument((string) $request->file('markdown_file')->get(), $locale),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Post::class);
        $validated = $this->validateForm($request);
        $actor = $request->user();
        abort_unless($actor, 403);
        $post = $this->posts->createFromValidated($validated, $actor, [
            'require_default_locale' => true,
            'audit_action' => 'post.create.web',
        ]);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post created.');
    }

    public function edit(Post $post): View
    {
        $this->authorize('view', $post);

        $post->load(['translations', 'categories']);

        return view('admin.posts.form', [
            'post' => $post,
            'translationsByLocale' => $this->translationsByLocale($post->translations),
            'locales' => $this->supportedLocales(),
            'isEdit' => true,
            'categories' => Category::query()->with('translations')->orderByDesc('id')->get(),
            'assets' => $this->assetOptionsWithSelected($post->featured_asset_id),
            'selectedCategoryIds' => $post->categories->pluck('id')->all(),
            'schedules' => PublishSchedule::query()->where('entity_type', 'post')->where('entity_id', $post->id)->orderByDesc('id')->limit(10)->get(),
            'previewTokens' => PreviewToken::query()->where('entity_type', 'post')->where('entity_id', $post->id)->orderByDesc('id')->limit(10)->get(),
            'templates' => ContentTemplate::query()
                ->where('entity_type', ContentTemplateService::ENTITY_POST)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['id', 'name', 'description']),
            'templateSource' => null,
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);
        $validated = $this->validateForm($request);
        $actor = $request->user();
        abort_unless($actor, 403);
        $post = $this->posts->updateFromValidated($post, $validated, $actor, [
            'require_default_locale' => true,
            'audit_action' => 'post.update.web',
        ]);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post updated.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $this->postWorkflow->destroy($post, $request, [
            'audit_action' => 'post.delete.web',
        ]);

        return redirect()->route('admin.posts.index')->with('status', 'Post deleted.');
    }

    public function duplicate(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('create', Post::class);
        $actor = $request->user();
        abort_unless($actor, 403);

        $copy = $this->posts->duplicate($post, $actor, [
            'audit_action' => 'post.duplicate.web',
        ]);

        return redirect()
            ->route('admin.posts.edit', $copy)
            ->with('status', 'Post duplicated.');
    }

    public function publish(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('publish', $post);

        $this->postWorkflow->publish($post, $request, [
            'audit_action' => 'post.publish.web',
        ]);

        return back()->with('status', 'Post published.');
    }

    public function unpublish(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('publish', $post);

        $this->postWorkflow->unpublish($post, $request, [
            'audit_action' => 'post.unpublish.web',
        ]);

        return back()->with('status', 'Post moved to draft.');
    }

    public function schedule(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('publish', $post);

        $validated = $request->validate([
            'action' => 'required|string|in:publish,unpublish',
            'due_at' => 'required|date|after:now',
        ]);

        $this->postWorkflow->schedule(
            $post,
            (string) $validated['action'],
            (string) $validated['due_at'],
            $request,
            [
                'audit_action' => 'post.schedule.web',
                'audit_context' => [
                    'action' => $validated['action'],
                    'due_at' => $validated['due_at'],
                ],
            ]
        );

        return back()->with('status', 'Schedule created.');
    }

    public function createPreviewToken(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('view', $post);

        $locale = (string) ($request->input('locale') ?: $this->defaultLocale());
        $preview = $this->postWorkflow->createPreviewToken($post, $locale, $request, [
            'audit_action' => 'post.preview_token.create.web',
        ]);

        return back()
            ->with('status', 'Preview link generated (24h).')
            ->with('preview_link', $preview['url'])
            ->with('action_modal', $request->input('modal_id'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Post::class);
        $actor = $request->user();
        abort_unless($actor, 403);
        $validated = $request->validate([
            'action' => 'required|string|in:unpublish,duplicate,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|min:1',
        ]);

        $report = $this->bulkActions->applyPosts(
            actor: $actor,
            action: (string) $validated['action'],
            ids: array_values(array_unique(array_map('intval', (array) ($validated['ids'] ?? [])))),
        );

        $status = sprintf(
            'Массовое действие "%s": успешно %d, ошибок %d.',
            $validated['action'],
            (int) ($report['success_count'] ?? 0),
            (int) ($report['failed_count'] ?? 0),
        );

        if (! empty($report['errors'])) {
            $status .= ' '.implode(' ', array_slice((array) $report['errors'], 0, 5));
        }

        return back()->with('status', $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request): array
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:draft,review,scheduled,published,archived',
            'featured_asset_id' => 'nullable|integer|exists:assets,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'translations' => 'required|array|min:1',
            'translations.*.title' => 'nullable|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255',
            'translations.*.content_format' => 'nullable|string|in:html,markdown',
            'translations.*.content_html' => 'nullable|string',
            'translations.*.content_markdown' => 'nullable|string',
            'translations.*.excerpt' => 'nullable|string',
            'translations.*.meta_title' => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string',
            'translations.*.canonical_url' => 'nullable|string|max:2048',
            'translations.*.custom_head_html' => 'nullable|string',
        ]);

        foreach ((array) ($validated['translations'] ?? []) as $locale => $translation) {
            if (! is_array($translation)) {
                continue;
            }
            $contentFormat = strtolower(trim((string) ($translation['content_format'] ?? 'html')));
            if ($contentFormat === 'markdown' && ! array_key_exists('content_markdown', $translation)) {
                throw ValidationException::withMessages([
                    "translations.{$locale}.content_markdown" => ['Markdown content is required when Markdown mode is selected.'],
                ]);
            }
        }

        return $validated;
    }

    private function resolveTemplate(Request $request): ?ContentTemplate
    {
        $templateId = (int) $request->query('from_template', 0);
        if ($templateId <= 0) {
            return null;
        }

        $template = ContentTemplate::query()
            ->where('id', $templateId)
            ->where('entity_type', ContentTemplateService::ENTITY_POST)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return null;
        }

        if (! ($request->user()?->can('posts:read') || $request->user()?->can('posts:write') || $request->user()?->hasRole('superadmin'))) {
            return null;
        }

        return $template;
    }

    /**
     * @param array<string, array<string, mixed>> $translations
     * @return array<string, object>
     */
    private function toTranslationObjects(array $translations): array
    {
        $result = [];
        foreach ($translations as $locale => $translation) {
            if (! is_array($translation)) {
                continue;
            }
            $payload = $translation;
            $payload['locale'] = $locale;
            $result[$locale] = (object) $payload;
        }

        return $result;
    }

    private function resolvePerPage(Request $request, string $sessionKey): int
    {
        $allowed = [10, 20, 50, 100];
        $requested = (int) $request->query('per_page', 0);
        if (in_array($requested, $allowed, true)) {
            $request->session()->put($sessionKey, $requested);

            return $requested;
        }

        $saved = (int) $request->session()->get($sessionKey, 20);

        return in_array($saved, $allowed, true) ? $saved : 20;
    }

    /**
     * @return Collection<int, Asset>
     */
    private function assetOptionsWithSelected(?int $selectedId = null): Collection
    {
        $assets = Asset::query()->orderByDesc('id')->limit(100)->get();

        if ($selectedId !== null && $selectedId > 0 && ! $assets->contains('id', $selectedId)) {
            $selected = Asset::query()->find($selectedId);
            if ($selected !== null) {
                $assets = $assets->prepend($selected);
            }
        }

        return $assets->unique('id')->values();
    }

    private function ensureCanWritePosts(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('superadmin') || $user->can('posts:write')), 403);
    }
}
