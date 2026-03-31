<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithLocalizedAdminForms;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ContentTemplate;
use App\Models\Page;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Modules\Content\Contracts\PageContentServiceContract;
use App\Modules\Content\Contracts\PageWorkflowServiceContract;
use App\Modules\Content\Services\ContentBulkActionService;
use App\Modules\Content\Services\ContentTemplateService;
use App\Modules\Extensibility\Registry\ModuleWidgetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageCrudController extends Controller
{
    use InteractsWithLocalizedAdminForms;

    public function __construct(
        private readonly ContentBulkActionService $bulkActions,
        private readonly ContentTemplateService $templates,
        private readonly PageContentServiceContract $pages,
        private readonly PageWorkflowServiceContract $pageWorkflow,
        private readonly ModuleWidgetRegistry $moduleWidgets,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Page::class);

        $perPage = $this->resolvePerPage($request, 'admin.pages.per_page');

        $pages = Page::query()
            ->with('translations')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.pages.index', [
            'pages' => $pages,
            'locales' => $this->supportedLocales(),
            'perPage' => $perPage,
            'perPageOptions' => [10, 20, 50, 100],
            'templates' => ContentTemplate::query()
                ->where('entity_type', ContentTemplateService::ENTITY_PAGE)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['id', 'name', 'description']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Page::class);

        $template = $this->resolveTemplate($request);
        $prefill = $template
            ? $this->templates->buildPrefillPayload($template)
            : ['entity' => [], 'translations' => []];
        $prefillTranslations = $this->toTranslationObjects($prefill['translations'] ?? []);

        return view('admin.pages.form', [
            'page' => new Page([
                'status' => 'draft',
                'page_type' => (string) ($prefill['entity']['page_type'] ?? 'landing'),
            ]),
            'translationsByLocale' => $prefillTranslations,
            'locales' => $this->supportedLocales(),
            'isEdit' => false,
            'assets' => Asset::query()->orderByDesc('id')->limit(200)->get(),
            'schedules' => collect(),
            'previewTokens' => collect(),
            'templates' => ContentTemplate::query()
                ->where('entity_type', ContentTemplateService::ENTITY_PAGE)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['id', 'name', 'description']),
            'templateSource' => $template,
            'moduleWidgetCatalog' => $this->moduleWidgets->catalog(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Page::class);
        $validated = $this->validateForm($request);
        $actor = $request->user();
        abort_unless($actor, 403);
        $page = $this->pages->createFromValidated($validated, $actor, [
            'require_default_locale' => true,
            'audit_action' => 'page.create.web',
        ]);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page created.');
    }

    public function edit(Page $page): View
    {
        $this->authorize('view', $page);

        $page->load('translations');

        return view('admin.pages.form', [
            'page' => $page,
            'translationsByLocale' => $this->translationsByLocale($page->translations),
            'locales' => $this->supportedLocales(),
            'isEdit' => true,
            'assets' => Asset::query()->orderByDesc('id')->limit(200)->get(),
            'schedules' => PublishSchedule::query()
                ->where('entity_type', 'page')
                ->where('entity_id', $page->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'previewTokens' => PreviewToken::query()
                ->where('entity_type', 'page')
                ->where('entity_id', $page->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'templates' => ContentTemplate::query()
                ->where('entity_type', ContentTemplateService::ENTITY_PAGE)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['id', 'name', 'description']),
            'templateSource' => null,
            'moduleWidgetCatalog' => $this->moduleWidgets->catalog(),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);
        $validated = $this->validateForm($request);
        $actor = $request->user();
        abort_unless($actor, 403);
        $page = $this->pages->updateFromValidated($page, $validated, $actor, [
            'require_default_locale' => true,
            'audit_action' => 'page.update.web',
        ]);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page updated.');
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('delete', $page);

        $this->pageWorkflow->destroy($page, $request, [
            'audit_action' => 'page.delete.web',
        ]);

        return redirect()->route('admin.pages.index')->with('status', 'Page deleted.');
    }

    public function duplicate(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('create', Page::class);
        $actor = $request->user();
        abort_unless($actor, 403);

        $copy = $this->pages->duplicate($page, $actor, [
            'audit_action' => 'page.duplicate.web',
        ]);

        return redirect()
            ->route('admin.pages.edit', $copy)
            ->with('status', 'Page duplicated.');
    }

    public function publish(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('publish', $page);

        $this->pageWorkflow->publish($page, $request, [
            'audit_action' => 'page.publish.web',
        ]);

        return back()->with('status', 'Page published.');
    }

    public function unpublish(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('publish', $page);

        $this->pageWorkflow->unpublish($page, $request, [
            'audit_action' => 'page.unpublish.web',
        ]);

        return back()->with('status', 'Page moved to draft.');
    }

    public function schedule(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('publish', $page);

        $validated = $request->validate([
            'action' => 'required|string|in:publish,unpublish',
            'due_at' => 'required|date|after:now',
        ]);

        $this->pageWorkflow->schedule(
            $page,
            (string) $validated['action'],
            (string) $validated['due_at'],
            $request,
            [
                'audit_action' => 'page.schedule.web',
                'audit_context' => [
                    'action' => $validated['action'],
                    'due_at' => $validated['due_at'],
                ],
            ]
        );

        return back()->with('status', 'Schedule created.');
    }

    public function createPreviewToken(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('view', $page);

        $locale = (string) ($request->input('locale') ?: $this->defaultLocale());
        $preview = $this->pageWorkflow->createPreviewToken($page, $locale, $request, [
            'audit_action' => 'page.preview_token.create.web',
        ]);

        return back()
            ->with('status', 'Preview link generated (24h).')
            ->with('preview_link', $preview['url'])
            ->with('action_modal', $request->input('modal_id'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Page::class);
        $actor = $request->user();
        abort_unless($actor, 403);
        $validated = $request->validate([
            'action' => 'required|string|in:unpublish,duplicate,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|min:1',
        ]);

        $report = $this->bulkActions->applyPages(
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
        return $request->validate([
            'status' => 'nullable|string|in:draft,review,scheduled,published,archived',
            'page_type' => 'nullable|string|max:32',
            'translations' => 'required|array|min:1',
            'translations.*.title' => 'nullable|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255',
            'translations.*.rich_html' => 'nullable|string',
            'translations.*.blocks_json' => 'nullable|string',
            'translations.*.meta_title' => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string',
            'translations.*.canonical_url' => 'nullable|string|max:2048',
            'translations.*.custom_head_html' => 'nullable|string',
        ]);
    }

    private function resolveTemplate(Request $request): ?ContentTemplate
    {
        $templateId = (int) $request->query('from_template', 0);
        if ($templateId <= 0) {
            return null;
        }

        $template = ContentTemplate::query()
            ->where('id', $templateId)
            ->where('entity_type', ContentTemplateService::ENTITY_PAGE)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return null;
        }

        if (! ($request->user()?->can('pages:read') || $request->user()?->can('pages:write') || $request->user()?->hasRole('superadmin'))) {
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
            $payload['content_blocks'] = $this->decodeJsonArrayText((string) ($translation['blocks_json'] ?? ''), 'template.blocks_json') ?? [];
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
}
