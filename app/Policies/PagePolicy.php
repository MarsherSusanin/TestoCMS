<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('pages:read') || $user->can('pages:write');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('pages:read') || $user->can('pages:write');
    }

    public function create(User $user): bool
    {
        return $user->can('pages:write');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('pages:write');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('pages:write');
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->can('pages:publish');
    }
}
