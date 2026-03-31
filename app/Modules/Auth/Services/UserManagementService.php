<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserManagementService
{
    public function canManageUsers(User $actor): bool
    {
        return $actor->hasRole('superadmin') || $actor->can('users:manage');
    }

    /**
     * @return array<int, string>
     */
    public function assignableRoleNamesFor(User $actor): array
    {
        $query = Role::query()->where('guard_name', 'web')->orderBy('name');
        if (! $actor->hasRole('superadmin')) {
            $query->where('name', '!=', 'superadmin');
        }

        return $query->pluck('name')->map(static fn ($name): string => (string) $name)->all();
    }

    public function assertCanManageTarget(User $actor, User $target): void
    {
        if (! $this->canManageUsers($actor)) {
            throw new AuthorizationException('Forbidden.');
        }

        if (! $actor->hasRole('superadmin') && $target->hasRole('superadmin')) {
            throw new AuthorizationException('Admin users cannot manage superadmin accounts.');
        }
    }

    /**
     * @param array<int, string> $requestedRoles
     * @return array<int, string>
     */
    public function normalizeAndValidateRoleAssignment(User $actor, array $requestedRoles): array
    {
        $available = $this->assignableRoleNamesFor($actor);
        $availableMap = array_fill_keys($available, true);

        $normalized = [];
        foreach ($requestedRoles as $roleName) {
            $role = trim((string) $roleName);
            if ($role === '') {
                continue;
            }
            if (! isset($availableMap[$role])) {
                throw ValidationException::withMessages([
                    'roles' => ['Одна или несколько ролей недоступны для назначения текущим пользователем.'],
                ]);
            }
            $normalized[$role] = true;
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'roles' => ['Выберите хотя бы одну роль.'],
            ]);
        }

        return array_keys($normalized);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $roles
     */
    public function createUser(User $actor, array $data, array $roles): User
    {
        if (! $this->canManageUsers($actor)) {
            throw new AuthorizationException('Forbidden.');
        }

        $roles = $this->normalizeAndValidateRoleAssignment($actor, $roles);

        $user = DB::transaction(function () use ($data, $roles): User {
            $user = User::query()->create([
                'name' => trim((string) ($data['name'] ?? '')),
                'login' => trim((string) ($data['login'] ?? '')),
                'email' => strtolower(trim((string) ($data['email'] ?? ''))),
                'password' => (string) ($data['password'] ?? ''),
                'status' => (string) ($data['status'] ?? 'active'),
            ]);

            $user->syncRoles($roles);

            return $user;
        });

        return $user->fresh(['roles']) ?? $user;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $roles
     */
    public function updateUser(User $actor, User $target, array $data, array $roles): User
    {
        $this->assertCanManageTarget($actor, $target);
        $roles = $this->normalizeAndValidateRoleAssignment($actor, $roles);

        $nextStatus = (string) ($data['status'] ?? $target->status ?? 'active');
        $this->assertSuperadminGuards($target, $roles, $nextStatus);

        DB::transaction(function () use ($target, $data, $roles, $nextStatus): void {
            $target->fill([
                'name' => trim((string) ($data['name'] ?? $target->name)),
                'login' => trim((string) ($data['login'] ?? $target->login)),
                'email' => strtolower(trim((string) ($data['email'] ?? $target->email))),
                'status' => $nextStatus,
            ]);
            $target->save();

            $target->syncRoles($roles);
        });

        return $target->fresh(['roles']) ?? $target;
    }

    public function updateStatus(User $actor, User $target, string $status): User
    {
        $this->assertCanManageTarget($actor, $target);

        $status = strtolower(trim($status));
        if (! in_array($status, ['active', 'blocked'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Unsupported status value.'],
            ]);
        }

        $roles = $target->roles()->pluck('name')->map(static fn ($role): string => (string) $role)->all();
        $this->assertSuperadminGuards($target, $roles, $status);

        $target->status = $status;
        $target->save();

        return $target->fresh(['roles']) ?? $target;
    }

    public function changePassword(User $actor, User $target, string $password, ?string $currentSessionId = null): int
    {
        $this->assertCanManageTarget($actor, $target);

        $target->password = $password;
        $target->save();

        return $this->revokeUserSessions($target, $currentSessionId);
    }

    /**
     * @param array<int, string> $nextRoles
     */
    private function assertSuperadminGuards(User $target, array $nextRoles, string $nextStatus): void
    {
        $isCurrentlySuperadmin = $target->hasRole('superadmin');
        $keepsSuperadmin = in_array('superadmin', $nextRoles, true);
        $willBeActive = $nextStatus === 'active';

        if ($isCurrentlySuperadmin && (! $keepsSuperadmin || ! $willBeActive)) {
            $otherActiveSuperadminExists = User::query()
                ->whereKeyNot($target->getKey())
                ->where('status', 'active')
                ->whereHas('roles', static fn ($query) => $query->where('name', 'superadmin'))
                ->exists();

            if (! $otherActiveSuperadminExists) {
                throw ValidationException::withMessages([
                    'roles' => ['Нельзя удалить роль superadmin или заблокировать последнего активного superadmin.'],
                    'status' => ['Нельзя удалить роль superadmin или заблокировать последнего активного superadmin.'],
                ]);
            }
        }
    }

    private function revokeUserSessions(User $target, ?string $currentSessionId = null): int
    {
        if ((string) config('session.driver') !== 'database') {
            return 0;
        }

        if (! Schema::hasTable('sessions')) {
            return 0;
        }

        $query = DB::table('sessions')->where('user_id', $target->id);
        if ($currentSessionId !== null && $currentSessionId !== '') {
            $query->where('id', '!=', $currentSessionId);
        }

        return (int) $query->delete();
    }
}
