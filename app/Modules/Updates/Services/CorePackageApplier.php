<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class CorePackageApplier
{
    public function __construct(private readonly CoreUpdateEnvironment $environment) {}

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
            $this->assertExpectedArtifact($release);
            $this->assertReleasePaths($releaseRoot);

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
            $release = $this->readReleaseJson($releaseRoot);
            $this->assertExpectedArtifact($release);
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

        $artifact = trim((string) ($decoded['artifact'] ?? ''));
        if ($artifact === '') {
            throw new RuntimeException('release.json artifact is required.');
        }

        $version = trim((string) ($decoded['version'] ?? ''));
        if ($version === '') {
            throw new RuntimeException('release.json version is required.');
        }

        return [
            'artifact' => $artifact,
            'version' => $version,
            'build' => trim((string) ($decoded['build'] ?? '')),
            'signed_at' => trim((string) ($decoded['signed_at'] ?? '')),
            'compat' => is_array($decoded['compat'] ?? null) ? $decoded['compat'] : [],
            'raw' => $decoded,
        ];
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function assertExpectedArtifact(array $release): void
    {
        $expectedArtifact = trim((string) config('updates.package_artifact', 'core-updater'));
        $artifact = trim((string) ($release['artifact'] ?? ''));

        if ($artifact !== $expectedArtifact) {
            throw new RuntimeException(sprintf(
                'Unsupported package artifact "%s". Expected "%s".',
                $artifact !== '' ? $artifact : 'unknown',
                $expectedArtifact
            ));
        }
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

        if (! is_dir($releaseRoot.DIRECTORY_SEPARATOR.'html_public')) {
            throw new RuntimeException('Package must contain html_public as the canonical public root source.');
        }
    }

    private function extractZipSecure(string $archivePath, string $extractDir): void
    {
        $zip = new ZipArchive;
        $openResult = $zip->open($archivePath);
        if ($openResult !== true) {
            throw new RuntimeException('Unable to open ZIP archive.');
        }

        try {
            $maxEntries = (int) config('updates.max_archive_entries', 20000);
            $maxUncompressedBytes = (int) config('updates.max_uncompressed_mb', 1024) * 1024 * 1024;

            if ($zip->numFiles > $maxEntries) {
                throw new RuntimeException('Update archive exceeds the maximum allowed entry count.');
            }

            $totalUncompressed = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = str_replace('\\', '/', (string) ($stat['name'] ?? $zip->getNameIndex($i)));
                if ($entryName === '' || str_contains($entryName, '../') || str_starts_with($entryName, '/') || str_contains($entryName, "\0")) {
                    throw new RuntimeException('Unsafe ZIP entry detected.');
                }

                $totalUncompressed += (int) ($stat['size'] ?? 0);
                if ($totalUncompressed > $maxUncompressedBytes) {
                    throw new RuntimeException('Update archive uncompressed size exceeds the allowed limit (possible zip bomb).');
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
