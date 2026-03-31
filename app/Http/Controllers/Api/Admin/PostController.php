<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Modules\Content\Contracts\PostContentServiceContract;
use App\Modules\Content\Contracts\PostWorkflowServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function __construct(
        private readonly PostContentServiceContract $posts,
        private readonly PostWorkflowServiceContract $postWorkflow,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) config('cms.max_per_page', 100), max(1, (int) $request->query('per_page', config('cms.default_per_page', 20))));

        $paginator = Post::query()
            ->with(['translations', 'categories'])
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
        $post = $this->posts->createFromValidated($validated, $actor, [
            'require_default_locale' => false,
            'audit_action' => 'post.create',
        ]);

        return response()->json(['data' => $post], 201);
    }

    public function show(Post $post): JsonResponse
    {
        $post->load(['translations', 'categories']);

        return response()->json(['data' => $post]);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        $validated = $this->validateInput($request);
        $actor = $request->user();
        abort_unless($actor, 403);
        $post = $this->posts->updateFromValidated($post, $validated, $actor, [
            'require_default_locale' => false,
            'audit_action' => 'post.update',
        ]);

        return response()->json(['data' => $post]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->postWorkflow->destroy($post, $request, [
            'audit_action' => 'post.delete',
        ]);

        return response()->json([], 204);
    }

    public function publish(Request $request, Post $post): JsonResponse
    {
        $post = $this->postWorkflow->publish($post, $request, [
            'audit_action' => 'post.publish',
        ]);

        return response()->json(['data' => $post]);
    }

    public function unpublish(Request $request, Post $post): JsonResponse
    {
        $post = $this->postWorkflow->unpublish($post, $request, [
            'audit_action' => 'post.unpublish',
        ]);

        return response()->json(['data' => $post]);
    }

    public function schedule(Request $request, Post $post): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:publish,unpublish',
            'due_at' => 'required|date|after:now',
        ]);

        $schedule = $this->postWorkflow->schedule(
            $post,
            (string) $validated['action'],
            (string) $validated['due_at'],
            $request,
            [
                'audit_action' => 'post.schedule',
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
        $validated = $request->validate([
            'status' => 'nullable|string|in:draft,review,scheduled,published,archived',
            'featured_asset_id' => 'nullable|integer|exists:assets,id',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|max:8',
            'translations.*.title' => 'required|string|max:255',
            'translations.*.slug' => 'required|string|max:255',
            'translations.*.content_format' => 'nullable|string|in:html,markdown',
            'translations.*.content_html' => 'nullable|string',
            'translations.*.content_markdown' => 'nullable|string',
            'translations.*.excerpt' => 'nullable|string',
            'translations.*.meta_title' => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string',
            'translations.*.canonical_url' => 'nullable|string|max:2048',
            'translations.*.robots_directives' => 'nullable|array',
            'translations.*.structured_data' => 'nullable|array',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
        ]);

        foreach ((array) ($validated['translations'] ?? []) as $index => $translation) {
            if (! is_array($translation)) {
                continue;
            }
            $contentFormat = strtolower(trim((string) ($translation['content_format'] ?? 'html')));
            if ($contentFormat === 'markdown' && ! array_key_exists('content_markdown', $translation)) {
                throw ValidationException::withMessages([
                    "translations.{$index}.content_markdown" => ['content_markdown is required when content_format is markdown.'],
                ]);
            }
        }

        return $validated;
    }

}
