<?php

namespace App\Modules\Content\Services;

use App\Models\Page;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Contracts\PageWorkflowServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageWorkflowService implements PageWorkflowServiceContract
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PageCacheService $pageCacheService,
    ) {}

    public function destroy(Page $page, Request $request, array $context = []): void
    {
        app(PublicationTransitionService::class)->destroy($page, $request, $context);
    }

    public function publish(Page $page, Request $request, array $context = []): Page
    {
        $result = app(PublicationTransitionService::class)->transition($page, 'publish', $request, $context);
        $this->pageCacheService->flushAll(false);

        return $result;
    }

    public function unpublish(Page $page, Request $request, array $context = []): Page
    {
        $result = app(PublicationTransitionService::class)->transition($page, 'unpublish', $request, $context);
        $this->pageCacheService->flushAll(false);

        return $result;
    }

    public function schedule(Page $page, string $action, string $dueAt, Request $request, array $context = []): PublishSchedule
    {
        $schedule = app(PublicationTransitionService::class)->schedule($page, $action, $dueAt, $request, $context);
        $this->pageCacheService->flushAll(false);

        return $schedule;
    }

    public function createPreviewToken(Page $page, string $locale, Request $request, array $context = []): array
    {
        $token = PreviewToken::query()->create([
            'entity_type' => 'page',
            'entity_id' => $page->id,
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
            'created_by' => $request->user()?->id,
        ]);

        $url = route('preview.show', ['token' => $token->token]).'?locale='.urlencode($locale);

        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'page.preview_token.create.web'),
            $page,
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
