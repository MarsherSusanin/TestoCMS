<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use RuntimeException;

class AdminProvisionerService
{
    /**
     * @param  array{name?: mixed, login?: mixed, email?: mixed, password?: mixed, status?: mixed}  $data
     */
    public function provision(array $data, bool $preferExistingSuperadmin = false): User
    {
        $payload = $this->normalizePayload($data);
        $target = $this->resolveTargetUser($payload['email'], $payload['login'], $preferExistingSuperadmin);

        $attributes = [
            'name' => $payload['name'],
            'login' => $payload['login'],
            'email' => $payload['email'],
            'password' => $payload['password'],
            'status' => $payload['status'],
        ];

        if ($target !== null) {
            $target->fill($attributes);
            $target->save();
            $admin = $target;
        } else {
            $admin = User::query()->create($attributes);
        }

        if (! $admin->hasRole('superadmin')) {
            $admin->assignRole('superadmin');
        }

        return $admin->refresh();
    }

    public function provisionFromEnvironment(bool $preferExistingSuperadmin = true): User
    {
        return $this->provision([
            'name' => trim((string) env('CMS_ADMIN_NAME', 'Super Admin')),
            'login' => trim((string) env('CMS_ADMIN_LOGIN', 'admin')),
            'email' => trim((string) env('CMS_ADMIN_EMAIL', '')),
            'password' => (string) env('CMS_ADMIN_PASSWORD', ''),
            'status' => 'active',
        ], $preferExistingSuperadmin);
    }

    private function resolveTargetUser(string $email, ?string $login, bool $preferExistingSuperadmin): ?User
    {
        $emailMatch = User::query()->where('email', $email)->first();
        $loginMatch = $login !== null
            ? User::query()->where('login', $login)->first()
            : null;

        if ($emailMatch !== null && $loginMatch !== null && $emailMatch->getKey() !== $loginMatch->getKey()) {
            throw new RuntimeException('Admin email and login belong to different existing users.');
        }

        if ($emailMatch !== null) {
            return $emailMatch;
        }

        if ($loginMatch !== null) {
            return $loginMatch;
        }

        if (! $preferExistingSuperadmin) {
            return null;
        }

        return User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', 'superadmin'))
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array{name?: mixed, login?: mixed, email?: mixed, password?: mixed, status?: mixed}  $data
     * @return array{name: string, login: ?string, email: string, password: string, status: string}
     */
    private function normalizePayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? 'Super Admin'));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $login = trim((string) ($data['login'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'active'));

        if ($email === '') {
            throw new RuntimeException('Admin email is required.');
        }

        if ($password === '') {
            throw new RuntimeException('Admin password is required.');
        }

        return [
            'name' => $name !== '' ? $name : 'Super Admin',
            'login' => $login !== '' ? $login : null,
            'email' => $email,
            'password' => $password,
            'status' => $status !== '' ? $status : 'active',
        ];
    }
}
