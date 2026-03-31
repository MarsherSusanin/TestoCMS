<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Modules\Content\Services\BlockSchemaValidator;
use App\Modules\Core\Contracts\BlockRendererContract;
use App\Modules\Core\Contracts\ContentRevisionServiceContract;
use App\Modules\LLM\Services\LlmGatewayService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmController extends Controller
{
    public function __construct(
        private readonly LlmGatewayService $llmGatewayService,
        private readonly BlockSchemaValidator $blockSchemaValidator,
        private readonly BlockRendererContract $blockRenderer,
        private readonly ContentRevisionServiceContract $revisionService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function generatePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|min:10',
            'locale' => 'nullable|string|max:8',
            'provider' => 'nullable|string|max:64',
            'save_as_draft' => 'nullable|boolean',
        ]);

        $result = $this->llmGatewayService->generate('generate-post', $validated, $request->user()?->id);

        if (($result['status'] ?? '') !== 'ok') {
            return response()->json(['error' => $result['message'] ?? 'Generation failed'], 422);
        }

        $draft = null;
        if ((bool) ($validated['save_as_draft'] ?? true)) {
            $locale = strtolower((string) ($validated['locale'] ?? config('cms.default_locale')));
            $rawText = $this->extractText($result['output'] ?? []);

            $post = Post::query()->create([
                'author_id' => $request->user()?->id,
                'status' => 'draft',
            ]);

            PostTranslation::query()->create([
                'post_id' => $post->id,
                'locale' => $locale,
                'title' => mb_substr($rawText ?: 'Generated draft', 0, 120),
                'slug' => 'draft-'.time().'-'.$post->id,
                'content_html' => '<p>'.e($rawText).'</p>',
                'content_plain' => $rawText,
            ]);

            $this->revisionService->snapshot('post', $post->id, $post->toArray(), $request->user()?->id, $locale);
            $this->auditLogger->log('llm.generate_post', $post, ['generation_id' => $result['generation_id'] ?? null], $request);

            $draft = $post->load('translations');
        }

        return response()->json([
            'data' => [
                'generation' => $result,
                'draft' => $draft,
                'draft_only' => true,
            ],
        ], 201);
    }

    public function generatePage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|min:10',
            'locale' => 'nullable|string|max:8',
            'provider' => 'nullable|string|max:64',
            'save_as_draft' => 'nullable|boolean',
        ]);

        $result = $this->llmGatewayService->generate('generate-page', $validated, $request->user()?->id);

        if (($result['status'] ?? '') !== 'ok') {
            return response()->json(['error' => $result['message'] ?? 'Generation failed'], 422);
        }

        $draft = null;
        if ((bool) ($validated['save_as_draft'] ?? true)) {
            $locale = strtolower((string) ($validated['locale'] ?? config('cms.default_locale')));
            $rawText = $this->extractText($result['output'] ?? []);

            $blocks = [
                [
                    'type' => 'rich_text',
                    'data' => [
                        'html' => '<p>'.e($rawText).'</p>',
                    ],
                ],
            ];
            $this->blockSchemaValidator->validateOrFail($blocks);

            $page = Page::query()->create([
                'author_id' => $request->user()?->id,
                'status' => 'draft',
                'page_type' => 'landing',
            ]);

            PageTranslation::query()->create([
                'page_id' => $page->id,
                'locale' => $locale,
                'title' => mb_substr($rawText ?: 'Generated page', 0, 120),
                'slug' => 'draft-'.time().'-'.$page->id,
                'content_blocks' => $blocks,
                'rendered_html' => $this->blockRenderer->render($blocks),
            ]);

            $this->revisionService->snapshot('page', $page->id, $page->toArray(), $request->user()?->id, $locale);
            $this->auditLogger->log('llm.generate_page', $page, ['generation_id' => $result['generation_id'] ?? null], $request);

            $draft = $page->load('translations');
        }

        return response()->json([
            'data' => [
                'generation' => $result,
                'draft' => $draft,
                'draft_only' => true,
            ],
        ], 201);
    }

    public function generateSeo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|min:10',
            'provider' => 'nullable|string|max:64',
        ]);

        $result = $this->llmGatewayService->generate('generate-seo', $validated, $request->user()?->id);

        if (($result['status'] ?? '') !== 'ok') {
            return response()->json(['error' => $result['message'] ?? 'Generation failed'], 422);
        }

        $text = $this->extractText($result['output'] ?? []);

        return response()->json([
            'data' => [
                'generation' => $result,
                'suggestions' => [
                    'meta_title' => mb_substr($text, 0, 60),
                    'meta_description' => mb_substr($text, 0, 160),
                ],
                'draft_only' => true,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $output
     */
    private function extractText(array $output): string
    {
        if (isset($output['output_text']) && is_string($output['output_text'])) {
            return trim($output['output_text']);
        }

        if (isset($output['content'][0]['text']) && is_string($output['content'][0]['text'])) {
            return trim($output['content'][0]['text']);
        }

        return trim((string) json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
