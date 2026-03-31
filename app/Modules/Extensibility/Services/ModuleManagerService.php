<?php

namespace App\Modules\Extensibility\Services;

use App\Models\CmsModule;
use App\Models\ModuleInstallLog;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Extensibility\DTO\InstalledModuleDto;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ModuleManagerService
{
    public function __construct(
        private readonly ModuleCacheService $moduleCache,
        private readonly ModuleRuntimeService $moduleRuntime,
        private readonly ModuleInstallerService $moduleInstaller,
        private readonly EnabledModulePublicRoutesLoader $publicRoutesLoader,
        private readonly ModuleSecuritySyncService $moduleSecuritySync,
        private readonly PageCacheService $pageCacheService,
    ) {}

    /**
     * @return array<int, InstalledModuleDto>
     */
    public function listInstalled(): array
    {
        return CmsModule::query()
            ->orderByDesc('enabled')
            ->orderBy('module_key')
            ->get()
            ->map(fn (CmsModule $module): InstalledModuleDto => new InstalledModuleDto(
                id: (int) $module->id,
                moduleKey: (string) $module->module_key,
                name: (string) $module->name,
                version: (string) $module->version,
                description: $module->description,
                author: $module->author,
                installPath: (string) $module->install_path,
                provider: (string) $module->provider,
                enabled: (bool) $module->enabled,
                status: (string) $module->status,
                lastError: $module->last_error,
                metadata: is_array($module->metadata) ? $module->metadata : [],
            ))
            ->all();
    }

    public function getById(int $id): CmsModule
    {
        return CmsModule::query()->findOrFail($id);
    }

    public function activate(CmsModule $module, ?int $userId = null): CmsModule
    {
        $this->assertModuleDirectoryExists($module);

        $module->forceFill([
            'enabled' => true,
            'status' => 'enabled',
            'last_error' => null,
        ])->save();

        try {
            $metadata = is_array($module->metadata) ? $module->metadata : [];
            $this->moduleSecuritySync->syncFromMetadata($metadata);
            $this->runModuleMigrations($module);
            $this->moduleCache->rebuildFromDatabase();
            $this->moduleRuntime->registerEnabledProvidersFromCache(app());
            $this->publicRoutesLoader->load();
            $routes = app('router')->getRoutes();
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
            $this->pageCacheService->flushAll();

            $this->logAction((string) $module->module_key, 'activate', 'success', [
                'version' => (string) $module->version,
            ], $userId);
        } catch (\Throwable $e) {
            $module->forceFill([
                'enabled' => false,
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ])->save();
            $this->moduleCache->rebuildFromDatabase();

            $this->logAction((string) $module->module_key, 'activate', 'failed', [
                'error' => $e->getMessage(),
            ], $userId);

            throw $e;
        }

        return $module->fresh() ?? $module;
    }

    public function deactivate(CmsModule $module, ?int $userId = null): CmsModule
    {
        $module->forceFill([
            'enabled' => false,
            'status' => 'disabled',
        ])->save();

        $this->moduleCache->rebuildFromDatabase();
        $this->pageCacheService->flushAll();

        $this->logAction((string) $module->module_key, 'deactivate', 'success', [
            'version' => (string) $module->version,
        ], $userId);

        return $module->fresh() ?? $module;
    }

    public function uninstall(CmsModule $module, bool $preserveData, ?int $userId = null): void
    {
        if ((bool) $module->enabled) {
            $this->deactivate($module, $userId);
            $module = $module->fresh() ?? $module;
        }

        $this->moduleInstaller->uninstall($module, $preserveData, $userId);
    }

    /**
     * @return array<int, ModuleInstallLog>
     */
    public function latestLogs(int $limit = 40): array
    {
        return ModuleInstallLog::query()
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->all();
    }

    private function assertModuleDirectoryExists(CmsModule $module): void
    {
        $path = (string) $module->install_path;
        if (! is_dir($path) && ! is_link($path)) {
            throw new RuntimeException('Module files are missing at path: '.$path);
        }
    }

    private function runModuleMigrations(CmsModule $module): void
    {
        $metadata = is_array($module->metadata) ? $module->metadata : [];
        $migrationsCapability = $metadata['capabilities']['migrations'] ?? true;
        if ($migrationsCapability === false || $migrationsCapability === null) {
            return;
        }

        $relative = 'database/migrations';
        if (is_string($migrationsCapability) && trim($migrationsCapability) !== '') {
            $relative = trim($migrationsCapability, '/\\');
        }

        $absolutePath = rtrim((string) $module->install_path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;
        if (! is_dir($absolutePath)) {
            return;
        }

        $files = File::files($absolutePath);
        if ($files === []) {
            return;
        }

        $relativeFromBase = str_replace(base_path().DIRECTORY_SEPARATOR, '', $absolutePath);
        $exitCode = Artisan::call('migrate', [
            '--path' => $relativeFromBase,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Module migration failed: '.trim(Artisan::output()));
        }
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
