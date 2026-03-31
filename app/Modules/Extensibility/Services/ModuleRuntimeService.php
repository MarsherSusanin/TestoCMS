<?php

namespace App\Modules\Extensibility\Services;

use App\Modules\Extensibility\Registry\AdminNavigationRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

class ModuleRuntimeService
{
    /**
     * @var array<string, array<int, string>>
     */
    private static array $autoloadPrefixes = [];

    private static bool $autoloadRegistered = false;

    public function __construct(
        private readonly ModuleCacheService $moduleCache,
        private readonly AdminNavigationRegistry $adminNavigation,
        private readonly ModuleSecuritySyncService $moduleSecuritySync,
    ) {}

    public function registerEnabledProvidersFromCache(Application $app): void
    {
        $modules = $this->moduleCache->loadEnabledModules();
        $this->moduleSecuritySync->syncEnabledModules($modules);

        $this->registerAutoloadPrefixes($modules);

        foreach ($modules as $moduleRow) {
            $provider = trim((string) ($moduleRow['provider'] ?? ''));
            $nav = $moduleRow['metadata']['admin']['nav'] ?? [];
            if (is_array($nav)) {
                $this->adminNavigation->registerMany($nav);
            }

            if ($provider === '') {
                continue;
            }

            if (($app->getLoadedProviders()[$provider] ?? false) === true) {
                continue;
            }

            try {
                if (! class_exists($provider)) {
                    throw new \RuntimeException('Provider class not found: '.$provider);
                }

                $app->register($provider);
            } catch (\Throwable $e) {
                Log::warning('Module provider registration failed', [
                    'provider' => $provider,
                    'module' => $moduleRow['module_key'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    private function registerAutoloadPrefixes(array $modules): void
    {
        foreach ($modules as $moduleRow) {
            $installPath = trim((string) ($moduleRow['install_path'] ?? ''));
            $psr4 = $moduleRow['metadata']['autoload']['psr-4'] ?? null;
            if ($installPath === '' || ! is_dir($installPath) || ! is_array($psr4)) {
                continue;
            }

            foreach ($psr4 as $prefix => $relativePath) {
                $prefix = (string) $prefix;
                $relativePath = trim((string) $relativePath);
                if ($prefix === '' || $relativePath === '') {
                    continue;
                }

                $resolved = rtrim($installPath.DIRECTORY_SEPARATOR.$relativePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                if (! is_dir($resolved)) {
                    continue;
                }

                self::$autoloadPrefixes[$prefix] ??= [];
                if (! in_array($resolved, self::$autoloadPrefixes[$prefix], true)) {
                    self::$autoloadPrefixes[$prefix][] = $resolved;
                }
            }
        }

        if (! self::$autoloadRegistered) {
            spl_autoload_register(static function (string $class): void {
                foreach (self::$autoloadPrefixes as $prefix => $directories) {
                    if (! str_starts_with($class, $prefix)) {
                        continue;
                    }

                    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))).'.php';
                    foreach ($directories as $directory) {
                        $file = $directory.$relative;
                        if (is_file($file)) {
                            require_once $file;

                            return;
                        }
                    }
                }
            });

            self::$autoloadRegistered = true;
        }
    }
}
