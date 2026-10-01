<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Services\ContentPublicationGuard;
use App\Modules\Content\Services\PageContentService;
use App\Modules\Content\Services\PostContentService;
use App\Modules\LLM\Services\LlmGatewayService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LlmController extends Controller
{
    public function __construct(
        private readonly LlmGatewayService $gateway,
        private readonly ContentPublicationGuard $publication,
        private readonly PageContentService $pages,
        private readonly PostContentService $posts,
        private readonly AuditLogger $audit,
    ) {}

    public function generatePost(Request $request): JsonResponse
    {
        return $this->generateContent($request, 'post');
    }

    public function generatePage(Request $request): JsonResponse
    {
        return $this->generateContent($request, 'page');
    }

    /** @return array<string, mixed> */
    private function validateInput(Request $request, bool $content): array
    {
        if (is_string($request->input('locale'))) {
            $request->merge(['locale' => strtolower(trim($request->input('locale')))]);
        }
        if (is_string($request->input('provider'))) {
            $request->merge(['provider' => strtolower(trim($request->input('provider')))]);
        }
        $rules = [
            'prompt' => ['required', 'string', 'min:10', 'max:'.(int) config('llm.max_input_chars', 12000)],
            'provider' => ['nullable', 'string', Rule::in(array_keys((array) config('llm.providers', [])))],
        ];
        if ($content) {
            $rules['locale'] = ['nullable', 'string', Rule::in((array) config('cms.supported_locales', ['en']))];
            $rules['save_as_draft'] = 'nullable|boolean';
        }

        return $request->validate($rules);
    }

    private function generateContent(Request $request, string $type): JsonResponse
    {
        $input = $this->validateInput($request, true);
        $save = (bool) ($input['save_as_draft'] ?? true);
        if ($save) {
            // Guard role and PAT scope before a paid call; service checks again
            // afterwards in case access changes while generation is running.
            $this->publication->assertCanMutate($request->user(), $type, null, 'draft');
        }
        $result = $this->gateway->generate('generate-'.$type, $input, $request->user()?->id);
        if (($result['status'] ?? '') !== 'ok') {
            return $this->failed($result);
        }
        $draft = null;
        if ($save) {
            $text = $result['text'];
            $locale = $input['locale'] ?? config('cms.default_locale');
            $translation = ['locale' => $locale, 'title' => mb_substr($text, 0, 120), 'slug' => 'draft-'.Str::uuid(),
                'content_format' => 'html', 'content_html' => '<p>'.e($text).'</p>',
                'content_blocks' => [['type' => 'rich_text', 'data' => ['html' => '<p>'.e($text).'</p>']]]];
            $payload = ['status' => 'draft', 'translations' => [$translation]];
            $draft = $type === 'page' ? $this->pages->createFromValidated($payload, $request->user()) : $this->posts->createFromValidated($payload, $request->user());
            $this->audit->log('llm.generate_'.$type, $draft, ['generation_id' => $result['generation_id']], $request);
        }

        return response()->json(['data' => ['generation' => $result, 'draft' => $draft, 'draft_only' => true]], 201);
    }

    public function generateSeo(Request $request): JsonResponse
    {
        $result = $this->gateway->generate('generate-seo', $this->validateInput($request, false), $request->user()?->id);
        if (($result['status'] ?? '') !== 'ok') {
            return $this->failed($result);
        }

        return response()->json(['data' => ['generation' => $result, 'suggestions' => [
            'meta_title' => mb_substr($result['text'], 0, 60), 'meta_description' => mb_substr($result['text'], 0, 160),
        ], 'draft_only' => true]]);
    }

    /** @param array<string, mixed> $result */
    private function failed(array $result): JsonResponse
    {
        return response()->json(['error' => $result['message'] ?? 'Generation failed',
            'error_code' => $result['error_code'] ?? 'llm_generation_failed', 'generation_id' => $result['generation_id'] ?? null], 422);
    }
}
