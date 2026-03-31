<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Modules\Content\Contracts\PageContentServiceContract;
use App\Modules\Core\Services\SiteChromeSettingsService;
use App\Modules\Core\Services\ThemeSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageStagePreviewController extends Controller
{
    public function __construct(
        private readonly PageContentServiceContract $pages,
        private readonly ThemeSettingsService $themeSettings,
        private readonly SiteChromeSettingsService $siteChromeSettings,
    ) {
    }

    public function render(Request $request): Response
    {
        $this->authorize('create', Page::class);

        $supportedLocales = array_values(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            config('cms.supported_locales', ['ru', 'en'])
        ));
        if ($supportedLocales === []) {
            $supportedLocales = ['ru', 'en'];
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($supportedLocales)],
            'page' => ['nullable', 'array'],
            'page.title' => ['nullable', 'string', 'max:255'],
            'page.slug' => ['nullable', 'string', 'max:255'],
            'page.status' => ['nullable', 'string', 'max:50'],
            'page.page_type' => ['nullable', 'string', 'max:50'],
            'translations' => ['required', 'array'],
            'translations.*' => ['nullable', 'array'],
            'translations.*.meta_title' => ['nullable', 'string', 'max:255'],
            'translations.*.meta_description' => ['nullable', 'string', 'max:1000'],
            'translations.*.canonical_url' => ['nullable', 'string', 'max:2048'],
            'translations.*.custom_head_html' => ['nullable', 'string'],
            'translations.*.nodes' => ['nullable'],
            'render' => ['nullable', 'array'],
            'render.device' => ['nullable', 'string', Rule::in(['desktop', 'tablet', 'mobile'])],
            'render.instrument' => ['nullable', 'boolean'],
            'render.include_theme' => ['nullable', 'boolean'],
            'render.include_header_footer' => ['nullable', 'boolean'],
            'chrome' => ['nullable', 'array'],
            'chrome.use_current_settings' => ['nullable', 'boolean'],
        ]);

        $locale = strtolower((string) $validated['locale']);
        app()->setLocale($locale);

        $pagePayload = is_array($validated['page'] ?? null) ? $validated['page'] : [];
        $translationsPayload = is_array($validated['translations'] ?? null) ? $validated['translations'] : [];
        $translationPayload = is_array($translationsPayload[$locale] ?? null) ? $translationsPayload[$locale] : [];
        $renderPayload = is_array($validated['render'] ?? null) ? $validated['render'] : [];
        $nodes = $translationPayload['nodes'] ?? [];
        if (! is_array($nodes)) {
            throw ValidationException::withMessages([
                'translations.'.$locale.'.nodes' => ['Nodes payload must be an array.'],
            ]);
        }

        $title = trim((string) ($pagePayload['title'] ?? ''));
        $slug = trim((string) ($pagePayload['slug'] ?? ''));
        if ($slug === '') {
            $slug = 'preview-page';
        }
        if ($title === '') {
            $title = 'Preview page';
        }

        $normalized = $this->pages->normalizeTranslations([
            [
                'locale' => $locale,
                'title' => $title,
                'slug' => $slug,
                'content_blocks' => $nodes,
                'meta_title' => $translationPayload['meta_title'] ?? null,
                'meta_description' => $translationPayload['meta_description'] ?? null,
                'canonical_url' => $translationPayload['canonical_url'] ?? null,
                'custom_head_html' => $translationPayload['custom_head_html'] ?? null,
            ],
        ], [
            'require_default_locale' => false,
            'assert_unique' => false,
            'render_context' => [
                'builder_stage_preview' => true,
                'instrument_nodes' => (bool) ($renderPayload['instrument'] ?? true),
            ],
        ]);

        $translationData = $normalized[$locale] ?? null;
        abort_unless(is_array($translationData), 422);
        $renderedHtml = (string) ($translationData['rendered_html'] ?? '');

        $page = new Page([
            'status' => (string) ($pagePayload['status'] ?? 'draft'),
            'page_type' => (string) ($pagePayload['page_type'] ?? 'landing'),
        ]);
        $page->updated_at = now();
        $page->published_at = null;

        $translation = new PageTranslation([
            'locale' => $locale,
            'title' => (string) ($translationData['title'] ?? $title),
            'slug' => (string) ($translationData['slug'] ?? $slug),
            'rendered_html' => $renderedHtml,
            'meta_title' => $translationData['meta_title'],
            'meta_description' => $translationData['meta_description'],
            'canonical_url' => $translationData['canonical_url'],
            'custom_head_html' => $translationData['custom_head_html'],
            'content_blocks' => $translationData['content_blocks'],
        ]);

        $seo = [
            'meta_title' => $translation->meta_title ?: ($translation->title.' | '.config('app.name')),
            'meta_description' => $translation->meta_description,
            'canonical_url' => $translation->canonical_url,
            'robots_directives' => [
                'index' => false,
                'follow' => false,
            ],
        ];

        $view = view('admin.pages.stage-preview', [
            'page' => $page,
            'translation' => $translation,
            'seo' => $seo,
            'structuredData' => null,
            'hreflangs' => [],
            'customHeadHtml' => $translation->custom_head_html,
            'isPreview' => true,
            'siteTheme' => $this->themeSettings->resolvedTheme(),
            'siteChrome' => $this->siteChromeSettings->resolvedChrome(),
            'stageDevice' => (string) ($renderPayload['device'] ?? 'desktop'),
            'stageInstrumented' => (bool) ($renderPayload['instrument'] ?? true),
            'stageRenderedHtml' => $renderedHtml,
        ]);

        return response($view->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
