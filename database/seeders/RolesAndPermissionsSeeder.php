<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'posts:read', 'posts:write', 'posts:publish',
            'pages:read', 'pages:write', 'pages:publish',
            'categories:read', 'categories:write',
            'assets:read', 'assets:write',
            'booking:read', 'booking:write', 'booking:manage', 'booking:settings',
            'llm:generate',
            'settings:read', 'settings:write',
            'users:manage',
            'roles:manage',
            'audit:read',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'superadmin' => $permissions,
            'admin' => array_values(array_filter($permissions, static fn (string $permission): bool => ! in_array($permission, ['settings:write', 'roles:manage'], true))),
            'editor' => [
                'posts:read', 'posts:write', 'posts:publish',
                'pages:read', 'pages:write', 'pages:publish',
                'categories:read', 'categories:write',
                'assets:read', 'assets:write',
                'llm:generate',
            ],
            'booking_manager' => [
                'booking:read', 'booking:write', 'booking:manage',
            ],
            'author' => [
                'posts:read', 'posts:write',
                'pages:read', 'pages:write',
                'assets:read',
            ],
            'observer' => [
                'posts:read',
                'pages:read',
                'categories:read',
                'assets:read',
                'audit:read',
                'settings:read',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissions);
        }
    }
}
