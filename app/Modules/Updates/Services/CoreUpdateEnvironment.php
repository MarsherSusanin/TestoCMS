<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\File;

class CoreUpdateEnvironment
{
    public function __construct(private readonly CoreUpdateSettingsService $settings) {}

    public function resolveExecutionMode(): string
    {
        $resolved = $this->settings->resolved();
        $configured = strtolower(trim((string) ($resolved['mode'] ?? 'auto')));
        if (! in_array($configured, ['auto', 'filesystem-updater', 'deploy-hook'], true)) {
            $configured = 'auto';
        }

        if ($configured !== 'auto') {
            return $configured;
        }

        return $this->isFilesystemUpdaterPossible() ? 'filesystem-updater' : 'deploy-hook';
    }

    public function isFilesystemUpdaterPossible(): bool
    {
        $basePath = $this->rootPath();
        if (! is_dir($basePath)) {
            return false;
        }

        foreach ($this->allowlistPaths() as $relative) {
            $target = $basePath.DIRECTORY_SEPARATOR.$relative;
            $checkPath = $this->writableCheckPath($target);
            if (! is_writable($checkPath)) {
                return false;
            }
        }

        $publicRoot = $this->activePublicRootPath();
        $publicRootCheck = is_dir($publicRoot) ? $publicRoot : dirname($publicRoot);
        if (! is_writable($publicRootCheck)) {
            return false;
        }

        foreach ($this->managedPublicPaths() as $relative) {
            $target = $publicRoot.DIRECTORY_SEPARATOR.$relative;
            $checkPath = $this->writableCheckPath($target);
            if (! is_writable($checkPath)) {
                return false;
            }
        }

        foreach (['storage', 'modules'] as $relative) {
            $target = $publicRoot.DIRECTORY_SEPARATOR.$relative;
            $checkPath = $this->writableCheckPath($target);
            if (! is_writable($checkPath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function allowlistPaths(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $path): string => trim((string) $path, '/\\'),
            (array) config('updates.allowlist_paths', [])
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @return array<int, string>
     */
    public function managedPublicPaths(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $path): string => trim((string) $path, '/\\'),
            (array) config('updates.managed_public_paths', [])
        ), static fn (string $value): bool => $value !== ''));
    }

    public function rootPath(): string
    {
        return rtrim((string) config('updates.base_path', base_path()), DIRECTORY_SEPARATOR);
    }

    public function activePublicRootPath(): string
    {
        return rtrim(public_path(), DIRECTORY_SEPARATOR);
    }

    public function storageRoot(): string
    {
        $root = rtrim((string) config('updates.storage_root', storage_path('app/private/core_updates')), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($root);

        return $root;
    }

    private function writableCheckPath(string $path): string
    {
        if (is_dir($path)) {
            return $path;
        }

        return dirname($path);
    }
}
