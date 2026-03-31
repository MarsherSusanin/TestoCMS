<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Services\UserManagementService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $users,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->ensureCanManage($actor);

        $users = User::query()
            ->with('roles')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.users.index', [
            'users' => $users,
            'canManage' => true,
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $request->user();
        $this->ensureCanManage($actor);

        return view('admin.users.form', [
            'isEdit' => false,
            'userModel' => new User(['status' => 'active']),
            'allRoleNames' => $this->users->assignableRoleNamesFor($actor),
            'assignedRoleNames' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->ensureCanManage($actor);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('users', 'login')],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'status' => ['required', 'string', Rule::in(['active', 'blocked'])],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
        ]);

        $user = $this->users->createUser($actor, $validated, (array) ($validated['roles'] ?? []));

        $this->auditLogger->log('users.create.web', $user, [
            'status' => $user->status,
            'roles' => $user->roles->pluck('name')->values()->all(),
        ], $request);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Пользователь создан.');
    }

    public function edit(Request $request, User $user): View
    {
        $actor = $request->user();
        $this->ensureCanManage($actor);
        $this->users->assertCanManageTarget($actor, $user);

        return view('admin.users.form', [
            'isEdit' => true,
            'userModel' => $user->load('roles'),
            'allRoleNames' => $this->users->assignableRoleNamesFor($actor),
            'assignedRoleNames' => $user->roles->pluck('name')->map(static fn ($name): string => (string) $name)->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->ensureCanManage($actor);
        $this->users->assertCanManageTarget($actor, $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('users', 'login')->ignore($user->id)],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'status' => ['required', 'string', Rule::in(['active', 'blocked'])],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'max:120'],
        ]);

        $user = $this->users->updateUser($actor, $user, $validated, (array) ($validated['roles'] ?? []));

        $this->auditLogger->log('users.update.web', $user, [
            'status' => $user->status,
            'roles' => $user->roles->pluck('name')->values()->all(),
        ], $request);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Пользователь обновлён.');
    }

    public function changePassword(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->ensureCanManage($actor);
        $this->users->assertCanManageTarget($actor, $user);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
        ]);

        $revokedSessions = $this->users->changePassword(
            $actor,
            $user,
            (string) $validated['password'],
            $request->session()->getId()
        );

        $this->auditLogger->log('users.password.change.web', $user, [
            'sessions_revoked' => $revokedSessions,
            'changed_by' => $actor?->id,
        ], $request);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Пароль обновлён.');
    }

    public function changeStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->ensureCanManage($actor);
        $this->users->assertCanManageTarget($actor, $user);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['active', 'blocked'])],
        ]);

        $user = $this->users->updateStatus($actor, $user, (string) $validated['status']);

        $this->auditLogger->log('users.status.change.web', $user, [
            'status' => $user->status,
        ], $request);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Статус пользователя обновлён.');
    }

    private function ensureCanManage(?User $actor): void
    {
        if (! $actor) {
            throw new AuthorizationException('Unauthorized.');
        }

        if (! $this->users->canManageUsers($actor)) {
            throw new AuthorizationException('Forbidden.');
        }
    }
}
