<?php

namespace App\Modules\Extensibility\Services;

use App\Models\CmsModule;
use App\Models\ModuleInstallLog;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Extensibility\DTO\ModuleManifestDto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ModuleInstallerService
{
    public function __construct(
        private readonly ModuleManifestParserService $manifestParser,
        private readonly ModuleCacheService $moduleCache,
        private readonly PageCacheService $pageCacheService,
    ) {
    }

    public function installFromZip(UploadedFile $zipFile, ?int $userId = null): CmsModule
    {
        $tmpRoot = $this->ensureTmpRoot();
        $jobDir = $tmpRoot.DIRECTORY_SEPARATOR.'job_'.Str::random(18);
        File::ensureDirectoryExists($jobDir);

        try {
            $archivePath = $jobDir.DIRECTORY_SEPARATOR.'module.zip';
            $zipFile->move($jobDir, 'module.zip');

            $extractDir = $jobDir.DIRECTORY_SEPARATOR.'extract';
            File::ensureDirectoryExists($extractDir);
            $this->extractZipSecure($archivePath, $extractDir);

            $moduleRoot = $this->discoverModuleRoot($extractDir);
            $manifest = $this->manifestParser->parseFromDirectory($moduleRoot);

            if (CmsModule::query()->where('module_key', $manifest->id)->exists()) {
                throw new RuntimeException('Module is already installed: '.$manifest->id);
            }

            $installPath = $this->moduleInstallPath($manifest->id);
            $recovered = $this->recoverExistingInstall(
                requestedManifest: $manifest,
                installPath: $installPath,
                requestedInstallType: 'zip',
            );
            if ($recovered !== null) {
                $this->logAction($manifest->id, 'install_zip', 'success', [
                    'version' => $recovered->version,
                    'install_path' => $installPath,
                    'recovered_existing_directory' => true,
                ], $userId);

                $this->moduleCache->rebuildFromDatabase();

                return $recovered;
            }

            $this->copyDirectorySafe($moduleRoot, $installPath);
            $this->publishModulePublicAssets($installPath, $manifest->id);

            $module = $this->createInstalledModuleRecord(
                manifest: $manifest,
                installPath: $installPath,
                checksum: is_file($archivePath) ? hash_file('sha256', $archivePath) : null,
                metadata: $manifest->toMetadataArray(),
            );

            $this->logAction($manifest->id, 'install_zip', 'success', [
                'version' => $manifest->version,
                'install_path' => $installPath,
            ], $userId);

            $this->moduleCache->rebuildFromDatabase();

            return $module;
        } catch (\Throwable $e) {
            $this->logAction('unknown', 'install_zip', 'failed', ['error' => $e->getMessage()], $userId);
            throw $e;
        } finally {
            File::deleteDirectory($jobDir);
        }
    }

    public function installFromLocalPath(string $sourcePath, ?int $userId = null, bool $useSymlink = false): CmsModule
    {
        $sourcePath = $this->normalizeRealPath($sourcePath);
        $this->assertWithinAllowedLocalRoots($sourcePath);

        $manifest = $this->manifestParser->parseFromDirectory($sourcePath);

        if (CmsModule::query()->where('module_key', $manifest->id)->exists()) {
            throw new RuntimeException('Module is already installed: '.$manifest->id);
        }

        $installPath = $this->moduleInstallPath($manifest->id);
        $recovered = $this->recoverExistingInstall(
            requestedManifest: $manifest,
            installPath: $installPath,
            requestedInstallType: 'local_path',
            requestedSourcePath: $sourcePath,
        );
        if ($recovered !== null) {
            $this->logAction($manifest->id, 'install_local', 'success', [
                'source_path' => $sourcePath,
                'install_path' => $installPath,
                'recovered_existing_directory' => true,
            ], $userId);

            $this->moduleCache->rebuildFromDatabase();

            return $recovered;
        }

        if ($useSymlink && (bool) config('modules.allow_symlink_dev', false)) {
            if (! symlink($sourcePath, $installPath)) {
                throw new RuntimeException('Failed to create symlink for module install.');
            }
        } else {
            $this->copyDirectorySafe($sourcePath, $installPath);
        }

        $this->publishModulePublicAssets($installPath, $manifest->id);

        $module = $this->createInstalledModuleRecord(
            manifest: $manifest,
            installPath: $installPath,
            checksum: null,
            metadata: array_merge($manifest->toMetadataArray(), [
                'install_source' => [
                    'type' => 'local_path',
                    'path' => $sourcePath,
                    'symlink' => $useSymlink && (bool) config('modules.allow_symlink_dev', false),
                ],
            ]),
        );

        $this->logAction($manifest->id, 'install_local', 'success', [
            'source_path' => $sourcePath,
            'install_path' => $installPath,
        ], $userId);

        $this->moduleCache->rebuildFromDatabase();

        return $module;
    }

    public function installBundled(string $sourcePath, ?int $userId = null): CmsModule
    {
        $sourcePath = $this->normalizeRealPath($sourcePath);

        $manifest = $this->manifestParser->parseFromDirectory($sourcePath);

        if (CmsModule::query()->where('module_key', $manifest->id)->exists()) {
            throw new RuntimeException('Module is already installed: '.$manifest->id);
        }

        $installPath = $this->moduleInstallPath($manifest->id);
        $recovered = $this->recoverExistingInstall(
            requestedManifest: $manifest,
            installPath: $installPath,
            requestedInstallType: 'bundled',
            requestedSourcePath: $sourcePath,
        );
        if ($recovered !== null) {
            $this->logAction($manifest->id, 'install_bundled', 'success', [
                'source_path' => $sourcePath,
                'install_path' => $installPath,
                'recovered_existing_directory' => true,
            ], $userId);

            $this->moduleCache->rebuildFromDatabase();

            return $recovered;
        }

        $this->copyDirectorySafe($sourcePath, $installPath);
        $this->publishModulePublicAssets($installPath, $manifest->id);

        $module = $this->createInstalledModuleRecord(
            manifest: $manifest,
            installPath: $installPath,
            checksum: null,
            metadata: array_merge($manifest->toMetadataArray(), [
                'install_source' => [
                    'type' => 'bundled',
                    'path' => $sourcePath,
                ],
            ]),
        );

        $this->logAction($manifest->id, 'install_bundled', 'success', [
            'source_path' => $sourcePath,
            'install_path' => $installPath,
        ], $userId);

        $this->moduleCache->rebuildFromDatabase();

        return $module;
    }

    public function updateFromZip(CmsModule $module, UploadedFile $zipFile, ?int $userId = null): CmsModule
    {
        $tmpRoot = $this->ensureTmpRoot();
        $jobDir = $tmpRoot.DIRECTORY_SEPARATOR.'job_'.Str::random(18);
        File::ensureDirectoryExists($jobDir);

        try {
            $archivePath = $jobDir.DIRECTORY_SEPARATOR.'module.zip';
            $zipFile->move($jobDir, 'module.zip');

            $extractDir = $jobDir.DIRECTORY_SEPARATOR.'extract';
            File::ensureDirectoryExists($extractDir);
            $this->extractZipSecure($archivePath, $extractDir);

            $moduleRoot = $this->discoverModuleRoot($extractDir);
            $manifest = $this->manifestParser->parseFromDirectory($moduleRoot);

            if ($manifest->id !== (string) $module->module_key) {
                throw new RuntimeException('Update archive module id mismatch.');
            }

            if (version_compare($manifest->version, (string) $module->version, '<=')) {
                throw new RuntimeException(sprintf('Module version must be higher than installed (%s).', $module->version));
            }

            $targetPath = (string) $module->install_path;
            if (! is_dir($targetPath) && ! is_link($targetPath)) {
                throw new RuntimeException('Installed module directory not found: '.$targetPath);
            }

            $backupPath = $jobDir.DIRECTORY_SEPARATOR.'backup_current';
            if (! @rename($targetPath, $backupPath)) {
                throw new RuntimeException('Cannot move current module directory to backup.');
            }

            try {
                $this->copyDirectorySafe($moduleRoot, $targetPath);
            } catch (\Throwable $copyError) {
                File::deleteDirectory($targetPath);
                @rename($backupPath, $targetPath);
                throw $copyError;
            }

            File::deleteDirectory($backupPath);
            $this->publishModulePublicAssets($targetPath, $manifest->id);

            $module->fill([
                'name' => $manifest->name,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'author' => $manifest->author,
                'provider' => $manifest->provider,
                'checksum' => is_file($archivePath) ? hash_file('sha256', $archivePath) : $module->checksum,
                'status' => $module->enabled ? 'enabled' : 'installed',
                'updated_at_module' => now(),
                'metadata' => array_merge($manifest->toMetadataArray(), [
                    'install_source' => array_merge((array) ($module->metadata['install_source'] ?? []), [
                        'last_update_type' => 'zip',
                    ]),
                ]),
                'last_error' => null,
            ]);
            $module->save();

            $this->logAction($manifest->id, 'update_zip', 'success', [
                'old_version' => $module->getOriginal('version'),
                'new_version' => $manifest->version,
            ], $userId);

            $this->moduleCache->rebuildFromDatabase();
            if ((bool) $module->enabled) {
                $this->pageCacheService->flushAll();
            }

            return $module->fresh() ?? $module;
        } catch (\Throwable $e) {
            $module->forceFill([
                'last_error' => $e->getMessage(),
                'status' => 'error',
            ])->save();

            $this->logAction((string) $module->module_key, 'update_zip', 'failed', ['error' => $e->getMessage()], $userId);
            throw $e;
        } finally {
            File::deleteDirectory($jobDir);
        }
    }

    public function uninstall(CmsModule $module, bool $preserveData, ?int $userId = null): void
    {
        $moduleKey = (string) $module->module_key;
        $installPath = (string) $module->install_path;

        if (is_link($installPath) || is_dir($installPath)) {
            if (is_link($installPath)) {
                @unlink($installPath);
            } else {
                File::deleteDirectory($installPath);
            }
        }

        $publicAssetsPath = public_path('modules'.DIRECTORY_SEPARATOR.$this->moduleDirName($moduleKey));
        if (is_dir($publicAssetsPath)) {
            File::deleteDirectory($publicAssetsPath);
        }

        $module->delete();

        $this->logAction($moduleKey, 'uninstall', 'success', [
            'preserve_data' => $preserveData,
        ], $userId);

        $this->moduleCache->rebuildFromDatabase();
        $this->pageCacheService->flushAll();
    }

    private function ensureTmpRoot(): string
    {
        $tmpRoot = (string) config('modules.upload_tmp_root', storage_path('app/private/module_uploads'));
        File::ensureDirectoryExists($tmpRoot);

        return $tmpRoot;
    }

    private function moduleInstallPath(string $moduleKey): string
    {
        $root = (string) config('modules.modules_root', base_path('modules'));
        File::ensureDirectoryExists($root);

        return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->moduleDirName($moduleKey);
    }

    private function moduleDirName(string $moduleKey): string
    {
        return str_replace('/', '--', strtolower($moduleKey));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createInstalledModuleRecord(
        ModuleManifestDto $manifest,
        string $installPath,
        ?string $checksum,
        array $metadata,
    ): CmsModule {
        return CmsModule::query()->create([
            'module_key' => $manifest->id,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'author' => $manifest->author,
            'install_path' => $installPath,
            'provider' => $manifest->provider,
            'checksum' => $checksum,
            'enabled' => false,
            'status' => 'installed',
            'installed_at' => now(),
            'updated_at_module' => now(),
            'metadata' => $metadata,
        ]);
    }

    private function recoverExistingInstall(
        ModuleManifestDto $requestedManifest,
        string $installPath,
        string $requestedInstallType,
        ?string $requestedSourcePath = null,
    ): ?CmsModule {
        if (! is_dir($installPath) && ! is_link($installPath)) {
            return null;
        }

        try {
            $existingManifest = $this->manifestParser->parseFromDirectory($installPath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Existing module directory cannot be recovered: '.$e->getMessage(), previous: $e);
        }

        if ($existingManifest->id !== $requestedManifest->id) {
            throw new RuntimeException(sprintf(
                'Target module directory belongs to another module: %s (%s)',
                $installPath,
                $existingManifest->id,
            ));
        }

        $this->publishModulePublicAssets($installPath, $existingManifest->id);

        $installSource = [
            'type' => 'recovered_existing_directory',
            'requested_install_type' => $requestedInstallType,
        ];

        if ($requestedInstallType !== 'zip' && $requestedSourcePath !== null && $requestedSourcePath !== '') {
            $installSource['requested_source_path'] = $requestedSourcePath;
        }

        return $this->createInstalledModuleRecord(
            manifest: $existingManifest,
            installPath: $installPath,
            checksum: null,
            metadata: array_merge($existingManifest->toMetadataArray(), [
                'install_source' => $installSource,
            ]),
        );
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

    private function discoverModuleRoot(string $extractDir): string
    {
        $directManifest = $extractDir.DIRECTORY_SEPARATOR.'module.json';
        if (is_file($directManifest)) {
            return $extractDir;
        }

        $candidates = [];
        $items = scandir($extractDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $extractDir.DIRECTORY_SEPARATOR.$item;
            if (! is_dir($path)) {
                continue;
            }

            if (is_file($path.DIRECTORY_SEPARATOR.'module.json')) {
                $candidates[] = $path;
            }
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        throw new RuntimeException('Cannot determine module root in archive. Ensure module.json is present in root or single top-level folder.');
    }

    private function copyDirectorySafe(string $source, string $target): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $target = rtrim($target, DIRECTORY_SEPARATOR);

        if (! is_dir($source)) {
            throw new RuntimeException('Source module directory not found: '.$source);
        }

        File::ensureDirectoryExists($target);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $pathname = (string) $item->getPathname();
            $relative = ltrim(str_replace($source, '', $pathname), DIRECTORY_SEPARATOR);
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            if (is_link($pathname)) {
                throw new RuntimeException('Module contains symbolic links. This is not allowed.');
            }

            if ($item->isDir()) {
                File::ensureDirectoryExists($destination);
                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            if (! copy($pathname, $destination)) {
                throw new RuntimeException('Failed to copy module file: '.$relative);
            }
        }
    }

    private function normalizeRealPath(string $path): string
    {
        $real = realpath($path);
        if (! is_string($real) || $real === '' || ! is_dir($real)) {
            throw new RuntimeException('Local module path is invalid or not a directory.');
        }

        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function assertWithinAllowedLocalRoots(string $sourcePath): void
    {
        $roots = config('modules.local_install_roots', [base_path('modules-dev')]);
        $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);

        foreach ((array) $roots as $root) {
            $realRoot = realpath((string) $root);
            if (! is_string($realRoot) || $realRoot === '') {
                continue;
            }
            $realRoot = rtrim($realRoot, DIRECTORY_SEPARATOR);
            if ($sourcePath === $realRoot || str_starts_with($sourcePath, $realRoot.DIRECTORY_SEPARATOR)) {
                return;
            }
        }

        throw new RuntimeException('Local module path is outside allowed roots.');
    }

    private function publishModulePublicAssets(string $installPath, string $moduleKey): void
    {
        $source = rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'public';
        if (! is_dir($source)) {
            return;
        }

        $target = public_path('modules'.DIRECTORY_SEPARATOR.$this->moduleDirName($moduleKey));
        if (is_dir($target)) {
            File::deleteDirectory($target);
        }

        $this->copyDirectorySafe($source, $target);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logAction(string $moduleKey, string $action, string $status, array $context = [], ?int $userId = null): void
    {
        ModuleInstallLog::query()->create([
            'module_key' => $moduleKey,
            'action' => $action,
            'status' => $status,
            'context' => $context,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }
}
