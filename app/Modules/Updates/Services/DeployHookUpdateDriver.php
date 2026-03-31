<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeployHookUpdateDriver
{
    public function __construct(private readonly CoreUpdateSettingsService $settings)
    {
    }

    /**
     * @param array<string, mixed>|null $pending
     * @param array<string, mixed>|null $available
     * @return array<string, mixed>
     */
    public function apply(?array $pending, ?array $available, ?int $actorId = null): array
    {
        $resolved = $this->settings->resolved();
        $hookUrl = trim((string) ($resolved['deploy_hook_url'] ?? ''));
        if ($hookUrl === '') {
            throw new RuntimeException('Deploy hook URL is required for deploy-hook mode.');
        }

        $targetVersion = trim((string) ($pending['version'] ?? $available['version'] ?? ''));
        if ($targetVersion === '') {
            throw new RuntimeException('No target version selected for deploy hook update.');
        }

        $payload = [
            'event' => 'cms.update.apply',
            'source' => $pending !== null ? (string) ($pending['source'] ?? 'manual') : 'cloud',
            'target_version' => $targetVersion,
            'current_version' => $this->settings->installedVersion(),
            'channel' => (string) ($resolved['channel'] ?? 'stable'),
            'requested_at' => now()->toIso8601String(),
        ];

        $request = Http::timeout((int) ($resolved['http_timeout'] ?? 15))->acceptJson();
        $hookToken = trim((string) ($resolved['deploy_hook_token'] ?? ''));
        if ($hookToken !== '') {
            $request = $request->withToken($hookToken);
        }

        $response = $request->post($hookUrl, $payload);
        if (! $response->successful()) {
            throw new RuntimeException('Deploy hook failed with HTTP '.$response->status().'.');
        }

        $state = $this->settings->state();
        $state['last_apply_at'] = now()->toIso8601String();
        $state['last_error'] = '';
        $this->settings->saveState($state, $actorId);

        return [
            'mode' => 'deploy-hook',
            'status' => 'queued',
            'target_version' => $targetVersion,
            'http_status' => $response->status(),
        ];
    }
}
