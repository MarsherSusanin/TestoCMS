<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
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
        return $user->can('categories:read') || $user->can('categories:write');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('categories:read') || $user->can('categories:write');
    }

    public function create(User $user): bool
    {
        return $user->can('categories:write');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories:write');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories:write');
    }
}
