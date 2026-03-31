<?php

namespace App\Modules\Extensibility\Services;

use App\Models\CmsModule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ModuleCacheService
{
    public function cachePath(): string
    {
        return (string) config('modules.cache_file', base_path('bootstrap/cache/cms_modules.php'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadEnabledModules(): array
    {
        $fromCache = $this->readCacheFile();
        $cachedModules = $this->filterUsableCachedModules($fromCache);

        if ($cachedModules !== null && $cachedModules !== [] && $cachedModules !== $fromCache) {
            $this->writeCacheFileBestEffort($cachedModules);
        }

        try {
            $databaseSnapshot = $this->loadFromDatabaseSnapshot();
        } catch (Throwable $e) {
            if ($cachedModules !== null) {
                Log::warning('Module cache fallback used after database lookup failed', [
                    'cache_path' => $this->cachePath(),
                    'cached_modules' => count($cachedModules),
                    'error' => $e->getMessage(),
                ]);

                return $cachedModules;
            }

            Log::warning('Module database lookup failed and no runtime cache is available', [
                'cache_path' => $this->cachePath(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (($databaseSnapshot['status'] ?? null) === 'missing_table') {
            if ($cachedModules !== null && $cachedModules !== []) {
                Log::warning('Ignoring stale module runtime cache because cms_modules table is missing', [
                    'cache_path' => $this->cachePath(),
                    'cached_modules' => count($cachedModules),
                ]);
            }

            return [];
        }

        if (($databaseSnapshot['status'] ?? null) !== 'ok') {
            return [];
        }

        $modules = $this->filterUsableCachedModules($databaseSnapshot['modules'] ?? []) ?? [];
        $this->writeCacheFileBestEffort($modules);

        return $modules;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rebuildFromDatabase(): array
    {
        $databaseSnapshot = $this->loadFromDatabaseSnapshot();
        if (($databaseSnapshot['status'] ?? null) !== 'ok') {
            return [];
        }

        $modules = $databaseSnapshot['modules'] ?? [];
        $this->writeCacheFile($modules);

        return $modules;
    }

    public function clearCacheFile(): void
    {
        $path = $this->cachePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    public function writeCacheFile(array $modules): void
    {
        $path = $this->cachePath();
        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'modules' => array_values($modules),
        ];

        $tmp = $path.'.tmp';
        $export = '<?php return '.var_export($payload, true).';'.PHP_EOL;
        file_put_contents($tmp, $export, LOCK_EX);
        @chmod($tmp, 0644);
        rename($tmp, $path);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function readCacheFile(): ?array
    {
        $path = $this->cachePath();
        if (! is_file($path)) {
            return null;
        }

        try {
            $payload = require $path;
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload) || ! is_array($payload['modules'] ?? null)) {
            return null;
        }

        return array_values(array_filter($payload['modules'], static fn (mixed $row): bool => is_array($row)));
    }

    /**
     * @return array{status: 'ok'|'missing_table', modules: array<int, array<string, mixed>>}
     */
    private function loadFromDatabaseSnapshot(): array
    {
        if (! Schema::hasTable('cms_modules')) {
            return [
                'status' => 'missing_table',
                'modules' => [],
            ];
        }

        return [
            'status' => 'ok',
            'modules' => CmsModule::query()
                ->where('enabled', true)
                ->orderBy('module_key')
                ->get()
                ->map(function (CmsModule $module): array {
                    $metadata = is_array($module->metadata) ? $module->metadata : [];

                    return [
                        'module_key' => (string) $module->module_key,
                        'provider' => (string) $module->provider,
                        'install_path' => (string) $module->install_path,
                        'version' => (string) $module->version,
                        'metadata' => $metadata,
                    ];
                })
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    private function writeCacheFileBestEffort(array $modules): void
    {
        try {
            $this->writeCacheFile($modules);
        } catch (Throwable $e) {
            Log::warning('Module cache refresh failed after successful database lookup', [
                'cache_path' => $this->cachePath(),
                'modules' => count($modules),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $modules
     * @return array<int, array<string, mixed>>|null
     */
    private function filterUsableCachedModules(?array $modules): ?array
    {
        if ($modules === null) {
            return null;
        }

        return array_values(array_filter($modules, function (array $module): bool {
            $installPath = trim((string) ($module['install_path'] ?? ''));

            return $installPath !== '' && is_dir($installPath);
        }));
    }
}
