<?php

namespace App\Modules\Content\Services;

use App\Models\Post;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Contracts\PostWorkflowServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostWorkflowService implements PostWorkflowServiceContract
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PageCacheService $pageCacheService,
    ) {}

    public function destroy(Post $post, Request $request, array $context = []): void
    {
        app(PublicationTransitionService::class)->destroy($post, $request, $context);
    }

    public function publish(Post $post, Request $request, array $context = []): Post
    {
        $result = app(PublicationTransitionService::class)->transition($post, 'publish', $request, $context);
        $this->pageCacheService->flushAll(false);

        return $result;
    }

    public function unpublish(Post $post, Request $request, array $context = []): Post
    {
        $result = app(PublicationTransitionService::class)->transition($post, 'unpublish', $request, $context);
        $this->pageCacheService->flushAll(false);

        return $result;
    }

    public function schedule(Post $post, string $action, string $dueAt, Request $request, array $context = []): PublishSchedule
    {
        $schedule = app(PublicationTransitionService::class)->schedule($post, $action, $dueAt, $request, $context);
        $this->pageCacheService->flushAll(false);

        return $schedule;
    }

    public function createPreviewToken(Post $post, string $locale, Request $request, array $context = []): array
    {
        $token = PreviewToken::query()->create([
            'entity_type' => 'post',
            'entity_id' => $post->id,
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
            'created_by' => $request->user()?->id,
        ]);

        $url = route('preview.show', ['token' => $token->token]).'?locale='.urlencode($locale);

        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'post.preview_token.create.web'),
            $post,
            is_array($context['audit_context'] ?? null)
                ? $context['audit_context']
                : ['preview_token_id' => $token->id],
            $request,
        );

        return [
            'token' => $token,
            'url' => $url,
        ];
    }
}
