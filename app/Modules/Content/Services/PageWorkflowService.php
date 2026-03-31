<?php

namespace App\Modules\Content\Services;

use App\Models\Page;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Contracts\PageWorkflowServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageWorkflowService implements PageWorkflowServiceContract
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PageCacheService $pageCacheService,
        private readonly SlugResolverService $slugResolver,
    ) {}

    public function destroy(Page $page, Request $request, array $context = []): void
    {
        $page->loadMissing('translations');
        $this->flushSlugCache($page);
        $page->delete();
        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'page.delete.web'),
            $page,
            is_array($context['audit_context'] ?? null) ? $context['audit_context'] : [],
            $request,
        );
        $this->pageCacheService->flushAll();
    }

    public function publish(Page $page, Request $request, array $context = []): Page
    {
        $page->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
        $page->loadMissing('translations');
        $this->flushSlugCache($page);

        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'page.publish.web'),
            $page,
            is_array($context['audit_context'] ?? null) ? $context['audit_context'] : [],
            $request,
        );
        $this->pageCacheService->flushAll();

        return $page->fresh('translations') ?? $page;
    }

    public function unpublish(Page $page, Request $request, array $context = []): Page
    {
        $page->update(['status' => 'draft']);
        $page->loadMissing('translations');
        $this->flushSlugCache($page);

        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'page.unpublish.web'),
            $page,
            is_array($context['audit_context'] ?? null) ? $context['audit_context'] : [],
            $request,
        );
        $this->pageCacheService->flushAll();

        return $page->fresh('translations') ?? $page;
    }

    public function schedule(Page $page, string $action, string $dueAt, Request $request, array $context = []): PublishSchedule
    {
        $schedule = DB::transaction(function () use ($page, $action, $dueAt, $request): PublishSchedule {
            $schedule = PublishSchedule::query()->create([
                'entity_type' => 'page',
                'entity_id' => $page->id,
                'action' => $action,
                'due_at' => $dueAt,
                'created_by' => $request->user()?->id,
            ]);

            $page->status = 'scheduled';
            $page->save();
            $page->loadMissing('translations');
            $this->flushSlugCache($page);

            return $schedule;
        });

        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'page.schedule.web'),
            $page,
            $this->resolveScheduleAuditContext($schedule, $action, $dueAt, $context),
            $request,
        );

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

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function resolveScheduleAuditContext(PublishSchedule $schedule, string $action, string $dueAt, array $context): array
    {
        $auditContext = is_array($context['audit_context'] ?? null)
            ? $context['audit_context']
            : ['action' => $action, 'due_at' => $dueAt];

        if (array_key_exists('schedule_id', $auditContext) && ($auditContext['schedule_id'] === null || $auditContext['schedule_id'] === '')) {
            $auditContext['schedule_id'] = $schedule->id;
        }

        return $auditContext;
    }

    private function flushSlugCache(Page $page): void
    {
        foreach ($page->translations as $translation) {
            $locale = strtolower((string) ($translation->locale ?? ''));
            $slug = (string) ($translation->slug ?? '');
            if ($locale === '' || $slug === '') {
                continue;
            }

            $this->slugResolver->flush($locale, $slug);
        }
    }
}
