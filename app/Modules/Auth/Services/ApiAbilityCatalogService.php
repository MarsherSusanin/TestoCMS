<?php

namespace App\Modules\Auth\Services;

class ApiAbilityCatalogService
{
    public const SURFACE_ADMIN = 'admin';

    public const SURFACE_CONTENT = 'content';

    /**
     * @return array<string, array<int, string>>
     */
    public function catalog(): array
    {
        return [
            self::SURFACE_ADMIN => [
                'posts:read',
                'posts:write',
                'posts:publish',
                'pages:read',
                'pages:write',
                'pages:publish',
                'categories:read',
                'categories:write',
                'assets:read',
                'assets:write',
                'llm:generate',
                'settings:read',
                'settings:write',
                'users:manage',
                'audit:read',
            ],
            self::SURFACE_CONTENT => [
                'content:read',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function surfaceLabels(): array
    {
        return [
            self::SURFACE_ADMIN => 'Admin API (/api/admin/v1/*)',
            self::SURFACE_CONTENT => 'Content API (/api/content/v1/*)',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function surfaceKeys(): array
    {
        return array_keys($this->catalog());
    }

    /**
     * @param  array<int, mixed>  $surfaces
     * @return array<int, string>
     */
    public function normalizeSurfaces(array $surfaces): array
    {
        $allowed = $this->surfaceKeys();
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $surface): string => strtolower(trim((string) $surface)),
            $surfaces
        ))));

        return array_values(array_filter($normalized, static fn (string $surface): bool => in_array($surface, $allowed, true)));
    }

    /**
     * @param  array<int, mixed>  $abilities
     * @return array<int, string>
     */
    public function normalizeAbilities(array $abilities): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $ability): string => trim((string) $ability),
            $abilities
        ))));
    }

    /**
     * @param  array<int, string>  $surfaces
     * @return array<int, string>
     */
    public function abilitiesForSurfaces(array $surfaces): array
    {
        $catalog = $this->catalog();
        $allowed = [];
        foreach ($surfaces as $surface) {
            foreach ($catalog[$surface] ?? [] as $ability) {
                $allowed[] = $ability;
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    public function inferSurfacesFromAbilities(array $abilities): array
    {
        if (in_array('*', $abilities, true)) {
            return $this->surfaceKeys();
        }

        $catalog = $this->catalog();
        $surfaces = [];
        foreach ($catalog as $surface => $surfaceAbilities) {
            foreach ($abilities as $ability) {
                if (in_array($ability, $surfaceAbilities, true)) {
                    $surfaces[] = $surface;
                    break;
                }
            }
        }

        return array_values(array_unique($surfaces));
    }

    /**
     * @return array<int, string>
     */
    public function allAbilities(): array
    {
        $all = [];
        foreach ($this->catalog() as $surfaceAbilities) {
            foreach ($surfaceAbilities as $ability) {
                $all[] = $ability;
            }
        }

        return array_values(array_unique($all));
    }
}
