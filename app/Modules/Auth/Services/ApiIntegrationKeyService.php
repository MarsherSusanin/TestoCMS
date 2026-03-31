<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

class ApiIntegrationKeyService
{
    public const NAME_PREFIX = 'ext:';

    public function __construct(
        private readonly ApiAbilityCatalogService $abilityCatalog,
    ) {
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function listManagedTokenModels(): Collection
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('name', 'like', self::NAME_PREFIX.'%')
            ->with('tokenable')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listManagedTokenRows(): Collection
    {
        return $this->listManagedTokenModels()->map(function (PersonalAccessToken $token): array {
            $abilities = $this->normalizeTokenAbilities($token->abilities);
            $surfaces = $this->abilityCatalog->inferSurfacesFromAbilities($abilities);
            $owner = $token->tokenable instanceof User ? $token->tokenable : null;

            return [
                'id' => (int) $token->id,
                'name' => $this->stripPrefix((string) $token->name),
                'raw_name' => (string) $token->name,
                'owner_id' => $owner?->id,
                'owner_label' => $owner ? sprintf('%s (%s)', $owner->name, $owner->email) : 'Неизвестный владелец',
                'abilities' => $abilities,
                'is_full_access' => in_array('*', $abilities, true),
                'surfaces' => $surfaces,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'last_used_at' => $token->last_used_at,
                'status' => $token->expires_at && $token->expires_at->isPast() ? 'expired' : 'active',
            ];
        });
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array{token: PersonalAccessToken, plain_text_token: string}
     */
    public function createManagedKey(User $owner, string $label, array $abilities, ?Carbon $expiresAt = null): array
    {
        $label = trim($label);
        if ($label === '') {
            throw new RuntimeException('Token label cannot be empty.');
        }

        $tokenName = self::NAME_PREFIX.$label;
        $newToken = $owner->createToken($tokenName, $abilities, $expiresAt);
        if (! $newToken instanceof NewAccessToken) {
            throw new RuntimeException('Failed to create token.');
        }

        return [
            'token' => $newToken->accessToken,
            'plain_text_token' => $newToken->plainTextToken,
        ];
    }

    public function revokeManagedKey(PersonalAccessToken $token): void
    {
        if (! $this->isManagedToken($token)) {
            throw new RuntimeException('Only managed integration tokens can be revoked from this screen.');
        }

        $token->delete();
    }

    public function isManagedToken(PersonalAccessToken $token): bool
    {
        return $token->tokenable_type === User::class
            && str_starts_with((string) $token->name, self::NAME_PREFIX);
    }

    public function stripPrefix(string $name): string
    {
        return str_starts_with($name, self::NAME_PREFIX)
            ? (string) substr($name, strlen(self::NAME_PREFIX))
            : $name;
    }

    /**
     * @param  mixed  $abilities
     * @return array<int, string>
     */
    private function normalizeTokenAbilities(mixed $abilities): array
    {
        if (! is_array($abilities)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $ability): string => trim((string) $ability),
            $abilities
        ))));
    }
}
