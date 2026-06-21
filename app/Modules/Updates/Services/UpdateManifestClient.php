<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class UpdateManifestClient
{
    public function __construct(
        private readonly CoreUpdateSettingsService $settings,
        private readonly CoreUpdateEnvironment $environment,
        private readonly CorePackageApplier $packageApplier,
        private readonly PackageSignatureVerifier $signatureVerifier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fetchRemoteManifest(): array
    {
        $resolved = $this->settings->resolved();
        $serverUrl = trim((string) ($resolved['server_url'] ?? ''));
        if ($serverUrl === '') {
            throw new RuntimeException('Update server URL is not configured.');
        }

        $manifestUrl = rtrim($serverUrl, '/').(string) config('updates.manifest_endpoint', '/api/updates/manifest');

        $response = Http::timeout((int) ($resolved['http_timeout'] ?? 15))
            ->acceptJson()
            ->get($manifestUrl, [
                'cms_version' => $this->settings->installedVersion(),
                'php' => PHP_VERSION,
                'channel' => (string) ($resolved['channel'] ?? 'stable'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Update server returned HTTP '.$response->status().'.');
        }

        $manifest = $response->json();
        if (! is_array($manifest)) {
            throw new RuntimeException('Invalid manifest response: JSON object expected.');
        }

        foreach (['version', 'channel', 'requires', 'sha256'] as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new RuntimeException('Manifest missing required key: '.$key);
            }
        }
        if (! is_array($manifest['requires'])) {
            throw new RuntimeException('Manifest key "requires" must be an object.');
        }

        $version = trim((string) ($manifest['version'] ?? ''));
        if ($version === '') {
            throw new RuntimeException('Manifest version is empty.');
        }

        if (trim((string) ($manifest['sha256'] ?? '')) === '') {
            throw new RuntimeException('Manifest is missing a package checksum (sha256).');
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function downloadCloudPackage(array $manifest): array
    {
        $resolved = $this->settings->resolved();
        $serverUrl = trim((string) ($resolved['server_url'] ?? ''));
        if ($serverUrl === '') {
            throw new RuntimeException('Update server URL is not configured.');
        }

        $version = trim((string) ($manifest['version'] ?? ''));
        if ($version === '') {
            throw new RuntimeException('Manifest version is empty.');
        }

        $packagesRoot = $this->environment->storageRoot().DIRECTORY_SEPARATOR.'packages';
        File::ensureDirectoryExists($packagesRoot);
        $zipPath = $packagesRoot.DIRECTORY_SEPARATOR.'cloud_'.$version.'_'.Str::random(8).'.zip';

        $packageUrl = trim((string) ($manifest['package_url'] ?? ''));
        if ($packageUrl === '') {
            $template = (string) config('updates.package_endpoint_template', '/api/updates/packages/{version}.zip');
            $packageUrl = rtrim($serverUrl, '/').str_replace('{version}', rawurlencode($version), $template);
        }

        $zipResponse = Http::timeout((int) ($resolved['http_timeout'] ?? 15))->get($packageUrl);
        if (! $zipResponse->successful()) {
            throw new RuntimeException('Failed to download cloud package: HTTP '.$zipResponse->status());
        }

        File::put($zipPath, $zipResponse->body());

        $sha256 = strtolower(trim((string) ($manifest['sha256'] ?? '')));
        $actualSha = strtolower((string) hash_file('sha256', $zipPath));
        if ($sha256 === '') {
            throw new RuntimeException('Manifest is missing a package checksum (sha256).');
        }
        if (! hash_equals($sha256, $actualSha)) {
            throw new RuntimeException('Package checksum mismatch.');
        }

        $signature = trim((string) ($manifest['signature'] ?? ''));
        if ($signature === '') {
            $sigTemplate = (string) config('updates.signature_endpoint_template', '/api/updates/packages/{version}.sig');
            $sigUrl = rtrim($serverUrl, '/').str_replace('{version}', rawurlencode($version), $sigTemplate);
            $sigResponse = Http::timeout((int) ($resolved['http_timeout'] ?? 15))->get($sigUrl);
            if (! $sigResponse->successful()) {
                throw new RuntimeException('Failed to download package signature: HTTP '.$sigResponse->status());
            }
            $signature = trim((string) $sigResponse->body());
        }

        $publicKey = trim((string) ($resolved['public_key'] ?? ''));
        if ($publicKey === '') {
            throw new RuntimeException('Public key is required for cloud update verification.');
        }

        if (! $this->signatureVerifier->verifyFile($zipPath, $signature, $publicKey)) {
            throw new RuntimeException('Cloud package signature verification failed.');
        }

        $inspection = $this->packageApplier->inspectArchive($zipPath);
        $release = $inspection['release'];
        if ((string) ($release['version'] ?? '') !== $version) {
            throw new RuntimeException('Release version mismatch between manifest and package.');
        }

        return [
            'source' => 'cloud',
            'version' => $version,
            'zip_path' => $zipPath,
            'sha256' => $actualSha,
            'release' => $release,
            'manifest' => $manifest,
            'downloaded_at' => now()->toIso8601String(),
        ];
    }
}
