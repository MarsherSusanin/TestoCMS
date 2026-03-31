<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Modules\Content\Contracts\PageContentServiceContract;
use App\Modules\Content\Contracts\PageWorkflowServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(
        private readonly PageContentServiceContract $pages,
        private readonly PageWorkflowServiceContract $pageWorkflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $paginator = Page::query()
            ->with('translations')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateInput($request);
        $actor = $request->user();
        abort_unless($actor, 403);
        $page = $this->pages->createFromValidated($validated, $actor, [
            'require_default_locale' => false,
            'audit_action' => 'page.create',
        ]);

        return response()->json(['data' => $page], 201);
    }

    public function show(Page $page): JsonResponse
    {
        $page->load('translations');

        return response()->json(['data' => $page]);
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $validated = $this->validateInput($request);
        $actor = $request->user();
        abort_unless($actor, 403);
        $page = $this->pages->updateFromValidated($page, $validated, $actor, [
            'require_default_locale' => false,
            'audit_action' => 'page.update',
        ]);

        return response()->json(['data' => $page]);
    }

    public function destroy(Request $request, Page $page): JsonResponse
    {
        $this->pageWorkflow->destroy($page, $request, [
            'audit_action' => 'page.delete',
        ]);

        return response()->json([], 204);
    }

    public function publish(Request $request, Page $page): JsonResponse
    {
        $page = $this->pageWorkflow->publish($page, $request, [
            'audit_action' => 'page.publish',
        ]);

        return response()->json(['data' => $page]);
    }

    public function unpublish(Request $request, Page $page): JsonResponse
    {
        $page = $this->pageWorkflow->unpublish($page, $request, [
            'audit_action' => 'page.unpublish',
        ]);

        return response()->json(['data' => $page]);
    }

    public function schedule(Request $request, Page $page): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:publish,unpublish',
            'due_at' => 'required|date|after:now',
        ]);

        $schedule = $this->pageWorkflow->schedule(
            $page,
            (string) $validated['action'],
            (string) $validated['due_at'],
            $request,
            [
                'audit_action' => 'page.schedule',
                'audit_context' => [
                    'schedule_id' => null,
                ],
            ]
        );

        return response()->json(['data' => $schedule], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateInput(Request $request): array
    {
        return $request->validate([
            'status' => 'nullable|string|in:draft,review,scheduled,published,archived',
            'page_type' => 'nullable|string|max:32',
            'custom_code' => 'nullable|array',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|max:8',
            'translations.*.title' => 'required|string|max:255',
            'translations.*.slug' => 'required|string|max:255',
            'translations.*.content_blocks' => 'nullable|array',
            'translations.*.meta_title' => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string',
            'translations.*.canonical_url' => 'nullable|string|max:2048',
            'translations.*.robots_directives' => 'nullable|array',
            'translations.*.structured_data' => 'nullable|array',
        ]);
    }
}
