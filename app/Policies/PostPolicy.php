<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
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
        return $user->can('posts:read') || $user->can('posts:write');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->can('posts:read') || $user->can('posts:write');
    }

    public function create(User $user): bool
    {
        return $user->can('posts:write');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('posts:write');
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('posts:write');
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->can('posts:publish');
    }
}
