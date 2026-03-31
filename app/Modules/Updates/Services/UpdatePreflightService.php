<?php

namespace App\Modules\Updates\Services;

use App\Models\CmsModule;

class UpdatePreflightService
{
    public function __construct(
        private readonly CoreUpdateSettingsService $settings,
        private readonly CoreUpdateEnvironment $environment,
    ) {
    }

    /**
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    public function run(string $targetVersion, array $package): array
    {
        $issues = [];
        $warnings = [];
        $resolved = $this->settings->resolved();
        $basePath = $this->environment->rootPath();
        $allowlist = $this->environment->allowlistPaths();

        $packageZipPath = trim((string) ($package['zip_path'] ?? ''));
        if ($packageZipPath === '' || ! is_file($packageZipPath)) {
            $issues[] = 'Update package ZIP is missing.';
        }

        foreach ($allowlist as $relative) {
            $target = $basePath.DIRECTORY_SEPARATOR.$relative;
            $checkPath = file_exists($target) ? $target : dirname($target);
            if (! is_writable($checkPath)) {
                $issues[] = sprintf('Path is not writable: %s', $relative);
            }
        }

        if ($packageZipPath !== '' && is_file($packageZipPath)) {
            $requiredBytes = (int) max(50 * 1024 * 1024, filesize($packageZipPath) * 4);
            $availableBytes = @disk_free_space($basePath);
            if (is_numeric($availableBytes) && $availableBytes < $requiredBytes) {
                $issues[] = sprintf('Insufficient disk space (%d bytes required).', $requiredBytes);
            }
        }

        $release = is_array($package['release'] ?? null) ? $package['release'] : [];
        $manifest = is_array($package['manifest'] ?? null) ? $package['manifest'] : [];
        $phpConstraint = trim((string) ($manifest['requires']['php'] ?? $release['compat']['php'] ?? '*'));
        if (! $this->versionSatisfiesConstraint(PHP_VERSION, $phpConstraint)) {
            $issues[] = sprintf('PHP %s does not satisfy %s', PHP_VERSION, $phpConstraint);
        }

        $currentVersion = $this->settings->installedVersion();
        $cmsFrom = trim((string) ($manifest['requires']['cms_from'] ?? $release['compat']['cms_from'] ?? $manifest['min_migration_version'] ?? ''));
        if ($cmsFrom !== '' && version_compare($currentVersion, $cmsFrom, '<')) {
            $issues[] = sprintf('Installed CMS version %s is below required %s', $currentVersion, $cmsFrom);
        }

        $enabledModules = CmsModule::query()->where('enabled', true)->get(['module_key', 'metadata']);
        foreach ($enabledModules as $module) {
            $metadata = is_array($module->metadata) ? $module->metadata : [];
            $constraint = trim((string) ($metadata['requires']['cms'] ?? '*'));
            if ($constraint === '' || $constraint === '*') {
                continue;
            }
            if (! $this->versionSatisfiesConstraint($targetVersion, $constraint)) {
                $issues[] = sprintf('Active module %s requires CMS %s', (string) $module->module_key, $constraint);
            }
        }

        if (($package['source'] ?? '') === 'cloud') {
            $publicKey = trim((string) ($resolved['public_key'] ?? ''));
            if ($publicKey === '') {
                $issues[] = 'Public key is required for cloud update signature verification.';
            }
        }

        return [
            'ok' => $issues === [],
            'issues' => $issues,
            'warnings' => $warnings,
            'mode' => $this->environment->resolveExecutionMode(),
        ];
    }

    private function versionSatisfiesConstraint(string $actualVersion, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        $chunks = preg_split('/\s*,\s*/', $constraint) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^\^(\d+)\.(\d+)\.(\d+)$/', $chunk, $m) === 1) {
                $major = (int) $m[1];
                $base = sprintf('%d.%d.%d', $m[1], $m[2], $m[3]);
                if ((int) explode('.', $actualVersion)[0] !== $major || version_compare($actualVersion, $base, '<')) {
                    return false;
                }
                continue;
            }

            if (preg_match('/^(>=|<=|>|<|=)?\s*([0-9]+(?:\.[0-9]+){0,2})$/', $chunk, $m) === 1) {
                $op = $m[1] !== '' ? $m[1] : '>=';
                $target = $m[2];
                if (! version_compare($actualVersion, $target, $op)) {
                    return false;
                }
                continue;
            }

            return false;
        }

        return true;
    }
}
