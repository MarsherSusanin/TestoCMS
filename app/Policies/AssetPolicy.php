<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
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
        return $user->can('assets:read') || $user->can('assets:write');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('assets:read') || $user->can('assets:write');
    }

    public function create(User $user): bool
    {
        return $user->can('assets:write');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('assets:write');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('assets:write');
    }
}
