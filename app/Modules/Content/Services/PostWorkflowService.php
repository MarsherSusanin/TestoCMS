<?php

namespace App\Modules\Content\Services;

use App\Models\Post;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Content\Contracts\PostWorkflowServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostWorkflowService implements PostWorkflowServiceContract
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PageCacheService $pageCacheService,
        private readonly SlugResolverService $slugResolver,
    ) {
    }

    public function destroy(Post $post, Request $request, array $context = []): void
    {
        $post->loadMissing('translations');
        $this->flushSlugCache($post);
        $post->delete();
        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'post.delete.web'),
            $post,
            is_array($context['audit_context'] ?? null) ? $context['audit_context'] : [],
            $request,
        );
        $this->pageCacheService->flushAll();
    }

    public function publish(Post $post, Request $request, array $context = []): Post
    {
        $post->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
        $post->loadMissing('translations');
        $this->flushSlugCache($post);

        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'post.publish.web'),
            $post,
            is_array($context['audit_context'] ?? null) ? $context['audit_context'] : [],
            $request,
        );
        $this->pageCacheService->flushAll();

        return $post->fresh('translations') ?? $post;
    }

    public function unpublish(Post $post, Request $request, array $context = []): Post
    {
        $post->update(['status' => 'draft']);
        $post->loadMissing('translations');
        $this->flushSlugCache($post);

        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'post.unpublish.web'),
            $post,
            is_array($context['audit_context'] ?? null) ? $context['audit_context'] : [],
            $request,
        );
        $this->pageCacheService->flushAll();

        return $post->fresh('translations') ?? $post;
    }

    public function schedule(Post $post, string $action, string $dueAt, Request $request, array $context = []): PublishSchedule
    {
        $schedule = DB::transaction(function () use ($post, $action, $dueAt, $request): PublishSchedule {
            $schedule = PublishSchedule::query()->create([
                'entity_type' => 'post',
                'entity_id' => $post->id,
                'action' => $action,
                'due_at' => $dueAt,
                'created_by' => $request->user()?->id,
            ]);

            $post->status = 'scheduled';
            $post->save();
            $post->loadMissing('translations');
            $this->flushSlugCache($post);

            return $schedule;
        });

        $this->auditLogger->log(
            (string) ($context['audit_action'] ?? 'post.schedule.web'),
            $post,
            $this->resolveScheduleAuditContext($schedule, $action, $dueAt, $context),
            $request,
        );

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

    /**
     * @param array<string, mixed> $context
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

    private function flushSlugCache(Post $post): void
    {
        $blogPrefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');

        foreach ($post->translations as $translation) {
            $locale = strtolower((string) ($translation->locale ?? ''));
            $slug = (string) ($translation->slug ?? '');
            if ($locale === '' || $slug === '') {
                continue;
            }

            $this->slugResolver->flush($locale, $blogPrefix.'/'.$slug);
        }
    }
}
