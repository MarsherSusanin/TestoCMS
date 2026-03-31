<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Services\ApiAbilityCatalogService;
use App\Modules\Auth\Services\ApiIntegrationKeyService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class ApiKeyController extends Controller
{
    public function __construct(
        private readonly ApiIntegrationKeyService $apiKeys,
        private readonly ApiAbilityCatalogService $abilityCatalog,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureCanManage($request);

        return view('admin.api-keys.index', [
            'tokens' => $this->apiKeys->listManagedTokenRows(),
            'ownerUsers' => User::query()
                ->where('status', 'active')
                ->whereHas('roles')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'surfaceLabels' => $this->abilityCatalog->surfaceLabels(),
            'abilityCatalog' => $this->abilityCatalog->catalog(),
            'defaultOwnerId' => (int) ($request->user()?->id ?? 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $surfaceKeys = $this->abilityCatalog->surfaceKeys();

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'owner_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(static fn ($query) => $query->where('status', 'active')),
            ],
            'surfaces' => ['required', 'array', 'min:1'],
            'surfaces.*' => ['required', 'string', Rule::in($surfaceKeys)],
            'full_access' => ['nullable', 'boolean'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['required', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $owner = User::query()
            ->where('status', 'active')
            ->find((int) $validated['owner_user_id']);
        if (! $owner) {
            throw ValidationException::withMessages([
                'owner_user_id' => ['Выбранный владелец ключа не найден или неактивен.'],
            ]);
        }

        $surfaces = $this->abilityCatalog->normalizeSurfaces((array) ($validated['surfaces'] ?? []));
        if ($surfaces === []) {
            throw ValidationException::withMessages([
                'surfaces' => ['Выберите хотя бы одну API-поверхность.'],
            ]);
        }

        $fullAccess = filter_var($validated['full_access'] ?? false, FILTER_VALIDATE_BOOL);
        $selectedAbilities = $this->abilityCatalog->normalizeAbilities((array) ($validated['abilities'] ?? []));

        if ($fullAccess) {
            $abilities = ['*'];
        } else {
            $allowedAbilities = $this->abilityCatalog->abilitiesForSurfaces($surfaces);
            $invalidAbilities = array_values(array_diff($selectedAbilities, $allowedAbilities));
            if ($invalidAbilities !== []) {
                throw ValidationException::withMessages([
                    'abilities' => ['Обнаружены недопустимые scopes: '.implode(', ', $invalidAbilities)],
                ]);
            }
            if ($selectedAbilities === []) {
                throw ValidationException::withMessages([
                    'abilities' => ['Выберите хотя бы один scope или включите режим Full access.'],
                ]);
            }
            $abilities = $selectedAbilities;
        }

        $expiresAt = ! empty($validated['expires_at'])
            ? Carbon::parse((string) $validated['expires_at'])
            : null;

        $created = $this->apiKeys->createManagedKey(
            owner: $owner,
            label: (string) $validated['label'],
            abilities: $abilities,
            expiresAt: $expiresAt
        );

        /** @var PersonalAccessToken $tokenModel */
        $tokenModel = $created['token'];
        $plainTextToken = (string) $created['plain_text_token'];

        $this->auditLogger->log('api_keys.create.web', $owner, [
            'token_id' => $tokenModel->id,
            'token_name' => $tokenModel->name,
            'owner_user_id' => $owner->id,
            'surfaces' => $surfaces,
            'full_access' => $fullAccess,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->toIso8601String(),
        ], $request);

        return redirect()
            ->route('admin.api-keys.index')
            ->with('status', 'API ключ создан.')
            ->with('api_key_created', [
                'token_id' => $tokenModel->id,
                'name' => $tokenModel->name,
                'owner' => sprintf('%s (%s)', $owner->name, $owner->email),
                'full_access' => $fullAccess,
                'abilities' => $abilities,
                'expires_at' => $expiresAt?->toDateTimeString(),
                'plain_token' => $plainTextToken,
            ]);
    }

    public function destroy(Request $request, PersonalAccessToken $accessToken): RedirectResponse
    {
        $this->ensureCanManage($request);

        if (! $this->apiKeys->isManagedToken($accessToken)) {
            abort(404);
        }

        $context = [
            'token_id' => $accessToken->id,
            'token_name' => $accessToken->name,
            'owner_user_id' => $accessToken->tokenable_id,
            'abilities' => is_array($accessToken->abilities) ? $accessToken->abilities : [],
        ];

        $this->apiKeys->revokeManagedKey($accessToken);

        $this->auditLogger->log('api_keys.revoke.web', null, $context, $request);

        return redirect()
            ->route('admin.api-keys.index')
            ->with('status', 'API ключ отозван.');
    }

    private function ensureCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('superadmin') || $user->can('settings:write')), 403);
    }
}
