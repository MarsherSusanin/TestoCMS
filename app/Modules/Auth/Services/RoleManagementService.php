<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagementService
{
    public function canViewRoleMatrix(User $actor): bool
    {
        return $actor->hasRole('superadmin') || $actor->can('users:manage');
    }

    public function canEditRoleMatrix(User $actor): bool
    {
        return $actor->hasRole('superadmin') || $actor->can('roles:manage');
    }

    public function assertCanViewRoleMatrix(User $actor): void
    {
        if (! $this->canViewRoleMatrix($actor)) {
            throw new AuthorizationException('Forbidden.');
        }
    }

    public function assertCanEditRoleMatrix(User $actor): void
    {
        if (! $this->canEditRoleMatrix($actor)) {
            throw new AuthorizationException('Forbidden.');
        }
    }

    /**
     * @return array<int, Role>
     */
    public function listRoles(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @return array<int, Permission>
     */
    public function listPermissions(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param array<int, string> $permissionNames
     */
    public function updateRolePermissions(User $actor, Role $role, array $permissionNames): Role
    {
        $this->assertCanEditRoleMatrix($actor);

        if ((string) $role->guard_name !== 'web') {
            throw ValidationException::withMessages([
                'permissions' => ['Разрешено редактирование только ролей guard=web.'],
            ]);
        }

        if ((string) $role->name === 'superadmin') {
            throw ValidationException::withMessages([
                'permissions' => ['Роль superadmin защищена и доступна только для чтения.'],
            ]);
        }

        $allowed = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->map(static fn ($name): string => (string) $name)
            ->all();
        $allowedMap = array_fill_keys($allowed, true);

        $normalized = [];
        foreach ($permissionNames as $permissionName) {
            $name = trim((string) $permissionName);
            if ($name === '') {
                continue;
            }
            if (! isset($allowedMap[$name])) {
                throw ValidationException::withMessages([
                    'permissions' => ['Выбрано неизвестное permission: '.$name],
                ]);
            }
            $normalized[$name] = true;
        }

        $role->syncPermissions(array_keys($normalized));

        return $role->fresh('permissions') ?? $role;
    }
}
