<?php

namespace App\Modules\Updates\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class ManagedPublicRootSyncService
{
    public function __construct(private readonly CoreUpdateEnvironment $environment) {}

    /**
     * @return array<int, string>
     */
    public function managedPaths(): array
    {
        return $this->environment->managedPublicPaths();
    }

    public function activePublicRootPath(): string
    {
        return rtrim(public_path(), DIRECTORY_SEPARATOR);
    }

    public function sourceHtmlPublicPath(?string $basePath = null): string
    {
        $basePath = rtrim($basePath ?? $this->environment->rootPath(), DIRECTORY_SEPARATOR);

        return $basePath.DIRECTORY_SEPARATOR.'html_public';
    }

    public function syncFromReleaseRoot(?string $basePath = null): void
    {
        $sourceRoot = $this->sourceHtmlPublicPath($basePath);
        if (! is_dir($sourceRoot)) {
            throw new RuntimeException('Managed public source directory is missing: '.$sourceRoot);
        }

        $targetRoot = $this->activePublicRootPath();
        if ($this->pathsReferToSameLocation($sourceRoot, $targetRoot)) {
            return;
        }

        File::ensureDirectoryExists($targetRoot);

        foreach ($this->managedPaths() as $relative) {
            $source = $sourceRoot.DIRECTORY_SEPARATOR.$relative;
            $target = $targetRoot.DIRECTORY_SEPARATOR.$relative;

            $this->deletePath($target);

            if (file_exists($source) || is_link($source)) {
                $this->copyPath($source, $target);
            }
        }
    }

    /**
     * @return array<int, array{path: string, exists: bool, type: string}>
     */
    public function snapshotManagedPaths(string $snapshotRoot): array
    {
        File::ensureDirectoryExists($snapshotRoot);

        $entries = [];
        $publicRoot = $this->activePublicRootPath();

        foreach ($this->managedPaths() as $relative) {
            $source = $publicRoot.DIRECTORY_SEPARATOR.$relative;
            $snapshot = $snapshotRoot.DIRECTORY_SEPARATOR.$relative;
            $exists = file_exists($source) || is_link($source);
            $type = 'missing';

            if ($exists) {
                if (is_link($source)) {
                    $type = 'link';
                    $linkTarget = readlink($source);
                    if ($linkTarget === false) {
                        throw new RuntimeException('Failed to read public symlink backup path: '.$relative);
                    }
                    File::ensureDirectoryExists(dirname($snapshot));
                    if (! @symlink($linkTarget, $snapshot)) {
                        throw new RuntimeException('Failed to snapshot public symlink: '.$relative);
                    }
                } elseif (is_dir($source)) {
                    $type = 'dir';
                    File::copyDirectory($source, $snapshot);
                } else {
                    $type = 'file';
                    File::ensureDirectoryExists(dirname($snapshot));
                    File::copy($source, $snapshot);
                }
            }

            $entries[] = [
                'path' => $relative,
                'exists' => $exists,
                'type' => $type,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{path?: mixed, exists?: mixed, type?: mixed}>  $entries
     */
    public function restoreManagedPaths(string $snapshotRoot, array $entries): void
    {
        $allowed = array_flip($this->managedPaths());
        $targetRoot = $this->activePublicRootPath();
        File::ensureDirectoryExists($targetRoot);

        foreach ($entries as $entry) {
            $relative = trim((string) ($entry['path'] ?? ''));
            if ($relative === '' || ! isset($allowed[$relative])) {
                continue;
            }

            $target = $targetRoot.DIRECTORY_SEPARATOR.$relative;
            $snapshot = $snapshotRoot.DIRECTORY_SEPARATOR.$relative;

            $this->deletePath($target);

            if (! empty($entry['exists'])) {
                if (! (file_exists($snapshot) || is_link($snapshot))) {
                    throw new RuntimeException('Snapshot path missing for public rollback: '.$relative);
                }
                $this->copyPath($snapshot, $target);
            }
        }
    }

    private function pathsReferToSameLocation(string $left, string $right): bool
    {
        return $this->normalizePath($left) === $this->normalizePath($right);
    }

    private function normalizePath(string $path): string
    {
        $real = realpath($path);

        return rtrim($real !== false ? $real : $path, DIRECTORY_SEPARATOR);
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
                throw new RuntimeException('Failed to read public symlink source: '.$source);
            }
            File::ensureDirectoryExists(dirname($target));
            if (! @symlink($linkTarget, $target)) {
                throw new RuntimeException('Failed to copy public symlink to target: '.$target);
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

        throw new RuntimeException('Unable to copy unknown public path type: '.$source);
    }
}
