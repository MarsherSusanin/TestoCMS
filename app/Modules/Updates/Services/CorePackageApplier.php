<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class CorePackageApplier
{
    public function __construct(private readonly CoreUpdateEnvironment $environment)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectArchive(string $archivePath): array
    {
        if (! is_file($archivePath)) {
            throw new RuntimeException('Archive not found for inspection: '.$archivePath);
        }

        $tmpRoot = $this->environment->storageRoot().DIRECTORY_SEPARATOR.'tmp';
        File::ensureDirectoryExists($tmpRoot);
        $jobDir = $tmpRoot.DIRECTORY_SEPARATOR.'inspect_'.Str::random(16);
        File::ensureDirectoryExists($jobDir);

        try {
            $extractDir = $jobDir.DIRECTORY_SEPARATOR.'extract';
            File::ensureDirectoryExists($extractDir);
            $this->extractZipSecure($archivePath, $extractDir);

            $releaseRoot = $this->discoverReleaseRoot($extractDir);
            $release = $this->readReleaseJson($releaseRoot);
            $this->assertReleasePaths($releaseRoot);

            $declaredChecksum = trim((string) ($release['checksum'] ?? ''));
            if ($declaredChecksum !== '') {
                $actual = strtolower((string) hash_file('sha256', $archivePath));
                if (! hash_equals(strtolower($declaredChecksum), $actual)) {
                    throw new RuntimeException('Release checksum in release.json does not match archive checksum.');
                }
            }

            return [
                'release' => $release,
                'sha256' => hash_file('sha256', $archivePath),
            ];
        } finally {
            File::deleteDirectory($jobDir);
        }
    }

    public function applyArchiveToFilesystem(string $archivePath): void
    {
        if (! is_file($archivePath)) {
            throw new RuntimeException('Archive file not found: '.$archivePath);
        }

        $tmpRoot = $this->environment->storageRoot().DIRECTORY_SEPARATOR.'tmp';
        File::ensureDirectoryExists($tmpRoot);
        $jobDir = $tmpRoot.DIRECTORY_SEPARATOR.'apply_'.Str::random(18);
        File::ensureDirectoryExists($jobDir);

        try {
            $extractDir = $jobDir.DIRECTORY_SEPARATOR.'extract';
            File::ensureDirectoryExists($extractDir);
            $this->extractZipSecure($archivePath, $extractDir);
            $releaseRoot = $this->discoverReleaseRoot($extractDir);
            $this->assertReleasePaths($releaseRoot);

            $basePath = $this->environment->rootPath();
            foreach ($this->environment->allowlistPaths() as $relative) {
                $source = $releaseRoot.DIRECTORY_SEPARATOR.$relative;
                if (! file_exists($source) && ! is_link($source)) {
                    continue;
                }

                $target = $basePath.DIRECTORY_SEPARATOR.$relative;
                $this->deletePath($target);
                $this->copyPath($source, $target);
            }
        } finally {
            File::deleteDirectory($jobDir);
        }
    }

    private function discoverReleaseRoot(string $extractDir): string
    {
        $rootCandidate = $extractDir.DIRECTORY_SEPARATOR.'release.json';
        if (is_file($rootCandidate)) {
            return $extractDir;
        }

        foreach (File::directories($extractDir) as $dir) {
            if (is_file($dir.DIRECTORY_SEPARATOR.'release.json')) {
                return $dir;
            }
        }

        throw new RuntimeException('release.json not found in archive root.');
    }

    /**
     * @return array<string, mixed>
     */
    private function readReleaseJson(string $releaseRoot): array
    {
        $path = rtrim($releaseRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'release.json';
        if (! is_file($path)) {
            throw new RuntimeException('release.json is missing in package root.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('release.json must be a JSON object.');
        }

        $version = trim((string) ($decoded['version'] ?? ''));
        if ($version === '') {
            throw new RuntimeException('release.json version is required.');
        }

        return [
            'version' => $version,
            'build' => trim((string) ($decoded['build'] ?? '')),
            'checksum' => trim((string) ($decoded['checksum'] ?? '')),
            'signed_at' => trim((string) ($decoded['signed_at'] ?? '')),
            'compat' => is_array($decoded['compat'] ?? null) ? $decoded['compat'] : [],
            'raw' => $decoded,
        ];
    }

    private function assertReleasePaths(string $releaseRoot): void
    {
        $allowed = array_flip(array_merge($this->environment->allowlistPaths(), ['release.json']));
        $forbidden = array_flip((array) config('updates.forbidden_paths', []));

        $entries = array_merge(File::files($releaseRoot), File::directories($releaseRoot));
        foreach ($entries as $entry) {
            $name = basename($entry);

            if (isset($forbidden[$name])) {
                throw new RuntimeException('Package contains forbidden path: '.$name);
            }

            if (! isset($allowed[$name])) {
                throw new RuntimeException('Package contains non-allowlisted top-level path: '.$name);
            }
        }
    }

    private function extractZipSecure(string $archivePath, string $extractDir): void
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($archivePath);
        if ($openResult !== true) {
            throw new RuntimeException('Unable to open ZIP archive.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = (string) $zip->getNameIndex($i);
                $entryName = str_replace('\\', '/', $entryName);
                if ($entryName === '' || str_contains($entryName, '../') || str_starts_with($entryName, '/') || str_contains($entryName, "\0")) {
                    throw new RuntimeException('Unsafe ZIP entry detected.');
                }
            }

            if (! $zip->extractTo($extractDir)) {
                throw new RuntimeException('Unable to extract ZIP archive.');
            }
        } finally {
            $zip->close();
        }
    }

    private function deletePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (is_dir($path)) {
            File::deleteDirectory($path);
        }
    }

    private function copyPath(string $source, string $target): void
    {
        if (is_link($source)) {
            $linkTarget = readlink($source);
            if ($linkTarget === false) {
                throw new RuntimeException('Failed to read symlink source: '.$source);
            }
            File::ensureDirectoryExists(dirname($target));
            if (! @symlink($linkTarget, $target)) {
                throw new RuntimeException('Failed to copy symlink to target: '.$target);
            }

            return;
        }

        if (is_dir($source)) {
            File::copyDirectory($source, $target);

            return;
        }

        if (is_file($source)) {
            File::ensureDirectoryExists(dirname($target));
            File::copy($source, $target);

            return;
        }

        throw new RuntimeException('Unable to copy unknown path type: '.$source);
    }
}
