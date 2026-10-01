<?php

namespace App\Modules\Setup\Services;

class DeploymentProfileService
{
    public const SHARED_HOSTING = 'shared_hosting';

    public const DOCKER_VPS = 'docker_vps';

    public const LOCAL = 'local';

    /**
     * @return array<string, array{label: string, description: string, queue_connection: string, cache_store: string, public_path: string}>
     */
    public function all(): array
    {
        return [
            self::LOCAL => [
                'label' => 'Локальная разработка',
                'description' => 'Artisan serve или локальный Docker с web root html_public.',
                'queue_connection' => 'sync',
                'cache_store' => 'file',
                'public_path' => 'html_public',
            ],
            self::SHARED_HOSTING => [
                'label' => 'Shared hosting (рекомендуется)',
                'description' => 'Классический PHP-хостинг с фиксированным public_html и cron для schedule:run.',
                'queue_connection' => 'sync',
                'cache_store' => 'file',
                'public_path' => '../public_html',
            ],
            self::DOCKER_VPS => [
                'label' => 'Docker / VPS',
                'description' => 'Выделенный сервер с отдельными app, web, queue и scheduler сервисами.',
                'queue_connection' => 'database',
                'cache_store' => 'file',
                'public_path' => 'html_public',
            ],
        ];
    }

    public function default(): string
    {
        $configured = (string) config('setup.deployment_profile', '');
        if (array_key_exists($configured, $this->all())) {
            return $configured;
        }
        if (app()->environment('local') || str_contains((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''), 'Development Server')) {
            return self::LOCAL;
        }

        return self::SHARED_HOSTING;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array{label: string, description: string, queue_connection: string, cache_store: string, public_path: string}
     */
    public function resolve(?string $profile): array
    {
        $normalized = $this->normalize($profile);

        return $this->all()[$normalized];
    }

    public function normalize(?string $profile): string
    {
        $candidate = is_string($profile) ? trim($profile) : '';

        return array_key_exists($candidate, $this->all())
            ? $candidate
            : $this->default();
    }
}
