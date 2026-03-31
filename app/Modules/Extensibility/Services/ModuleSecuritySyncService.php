<?php

namespace App\Modules\Extensibility\Services;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ModuleSecuritySyncService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function syncFromMetadata(array $metadata): void
    {
        if (! $this->isReady()) {
            return;
        }

        $security = is_array($metadata['security'] ?? null) ? $metadata['security'] : [];
        if ($security === []) {
            return;
        }

        $permissions = $this->normalizePermissionList($security['permissions'] ?? []);
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = is_array($security['roles'] ?? null) ? $security['roles'] : [];
        foreach ($roles as $roleName => $rolePermissions) {
            $roleName = trim((string) $roleName);
            if ($roleName === '') {
                continue;
            }

            $normalizedRolePermissions = $this->normalizePermissionList($rolePermissions);
            foreach ($normalizedRolePermissions as $permission) {
                Permission::query()->firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }

            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $currentPermissions = $role->permissions()
                ->pluck('name')
                ->map(static fn (mixed $permission): string => (string) $permission)
                ->all();

            $missingPermissions = array_values(array_diff($normalizedRolePermissions, $currentPermissions));
            if ($missingPermissions !== []) {
                $role->givePermissionTo($missingPermissions);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    public function syncEnabledModules(array $modules): void
    {
        foreach ($modules as $moduleRow) {
            $metadata = is_array($moduleRow['metadata'] ?? null) ? $moduleRow['metadata'] : [];
            $this->syncFromMetadata($metadata);
        }
    }

    private function isReady(): bool
    {
        try {
            return Schema::hasTable('permissions') && Schema::hasTable('roles');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizePermissionList(mixed $permissions): array
    {
        if (! is_array($permissions)) {
            return [];
        }

        $normalized = array_values(array_filter(array_map(
            static fn (mixed $permission): string => trim((string) $permission),
            $permissions
        )));

        return array_values(array_unique($normalized));
    }
}
