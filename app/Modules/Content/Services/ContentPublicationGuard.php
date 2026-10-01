<?php

namespace App\Modules\Content\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\PublishSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/** Shared by CRUD, workflow, bulk actions and integrations. */
class ContentPublicationGuard
{
    public function assertCanMutate(User $actor, string $entityType, ?Model $entity, string $nextStatus): void
    {
        $actor = $this->currentActor($actor);

        Gate::forUser($actor)->authorize($entity === null ? 'create' : 'update', $entity ?? ($entityType === 'page' ? Page::class : Post::class));
        if ($this->accessToken($actor) instanceof PersonalAccessToken) {
            abort_unless($actor->tokenCan($entityType.'s:write'), 403, 'Writing token scope is required.');
        }

        $cancelsSchedule = $entity !== null
            && $entity->getAttribute('status') !== $nextStatus
            && in_array($nextStatus, ['draft', 'review', 'archived'], true)
            && PublishSchedule::query()->pending()->where('entity_type', $entityType)->where('entity_id', $entity->getKey())->exists();

        if ($entity?->getAttribute('status') === 'published' || $nextStatus === 'published' || $cancelsSchedule) {
            $this->assertCanPublish($actor, $entityType, $entity);
        }
    }

    public function assertCanPublish(User $actor, string $entityType, ?Model $entity = null): void
    {
        $actor = $this->currentActor($actor);
        $target = $entity ?? ($entityType === 'page' ? new Page : new Post);
        Gate::forUser($actor)->authorize('publish', $target);

        if ($this->accessToken($actor) instanceof PersonalAccessToken) {
            abort_unless($actor->tokenCan($entityType.'s:publish'), 403, 'Publishing token scope is required.');
        }
    }

    public function assertCanDelete(User $actor, string $entityType, Model $entity): void
    {
        $actor = $this->currentActor($actor);
        Gate::forUser($actor)->authorize('delete', $entity);
        if ($this->accessToken($actor) instanceof PersonalAccessToken) {
            abort_unless($actor->tokenCan($entityType.'s:write'), 403, 'Writing token scope is required.');
        }
        if ($entity->getAttribute('status') === 'published') {
            $this->assertCanPublish($actor, $entityType, $entity);
        }
    }

    private function currentActor(User $actor): User
    {
        $current = $actor->fresh();
        abort_unless($current?->status === 'active', 403, 'Account is blocked.');
        $token = $this->accessToken($actor);
        if ($token instanceof PersonalAccessToken) {
            $token = $token->fresh();
            abort_unless($token !== null, 401, 'Authentication has expired.');
        }
        if ($token !== null) {
            $current->withAccessToken($token);
        }

        return $current;
    }

    private function accessToken(User $actor): PersonalAccessToken|TransientToken|null
    {
        // Sanctum also uses TransientToken for browser authentication and null
        // for ordinary web sessions despite HasApiTokens' narrower PHPDoc.
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $actor->currentAccessToken();

        return $token;
    }
}
