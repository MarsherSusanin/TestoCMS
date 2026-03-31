<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Services\RoleManagementService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleManagementService $roles,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->ensureCanView($actor);

        return view('admin.users.roles.index', [
            'roles' => $this->roles->listRoles(),
            'canEditMatrix' => $this->roles->canEditRoleMatrix($actor),
        ]);
    }

    public function edit(Request $request, Role $role): View
    {
        $actor = $request->user();
        $this->ensureCanView($actor);

        abort_unless((string) $role->guard_name === 'web', 404);

        return view('admin.users.roles.edit', [
            'role' => $role->load('permissions'),
            'allPermissions' => $this->roles->listPermissions(),
            'canEditMatrix' => $this->roles->canEditRoleMatrix($actor),
            'isSuperadminRole' => (string) $role->name === 'superadmin',
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $actor = $request->user();
        $this->ensureCanEdit($actor);

        abort_unless((string) $role->guard_name === 'web', 404);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['required', 'string', 'max:120', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);

        $updatedRole = $this->roles->updateRolePermissions(
            $actor,
            $role,
            (array) ($validated['permissions'] ?? [])
        );

        $this->auditLogger->log('roles.permissions.update.web', $updatedRole, [
            'role' => (string) $updatedRole->name,
            'permissions' => $updatedRole->permissions->pluck('name')->values()->all(),
        ], $request);

        return redirect()->route('admin.users.roles.edit', $updatedRole)->with('status', 'Права роли обновлены.');
    }

    private function ensureCanView(?User $actor): void
    {
        if (! $actor) {
            throw new AuthorizationException('Unauthorized.');
        }

        $this->roles->assertCanViewRoleMatrix($actor);
    }

    private function ensureCanEdit(?User $actor): void
    {
        if (! $actor) {
            throw new AuthorizationException('Unauthorized.');
        }

        $this->roles->assertCanEditRoleMatrix($actor);
    }
}
