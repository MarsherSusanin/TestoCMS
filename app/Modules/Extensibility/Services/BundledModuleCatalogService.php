<?php

namespace App\Modules\Extensibility\Services;

use App\Models\CmsModule;
use Illuminate\Support\Facades\File;

class BundledModuleCatalogService
{
    public function __construct(private readonly ModuleManifestParserService $manifestParser) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $root = (string) config('modules.bundled_root', base_path('bundled-modules'));
        if (! is_dir($root)) {
            return [];
        }

        $installed = CmsModule::query()
            ->get()
            ->keyBy(static fn (CmsModule $module): string => strtolower((string) $module->module_key));

        $items = [];
        foreach (File::directories($root) as $directory) {
            try {
                $manifest = $this->manifestParser->parseFromDirectory($directory);
            } catch (\Throwable) {
                continue;
            }

            $installedModule = $installed->get(strtolower($manifest->id));
            $recoverableInstallPath = $installedModule === null
                ? $this->recoverableInstallPathForManifest($manifest->id)
                : null;

            $items[] = [
                'module_key' => $manifest->id,
                'route_key' => str_replace('/', '--', strtolower($manifest->id)),
                'name' => $manifest->name,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'author' => $manifest->author,
                'provider' => $manifest->provider,
                'source_path' => $directory,
                'metadata' => $manifest->toMetadataArray(),
                'installed' => $installedModule !== null,
                'enabled' => (bool) ($installedModule?->enabled ?? false),
                'installed_module_id' => $installedModule?->id,
                'recoverable' => $recoverableInstallPath !== null,
                'recoverable_install_path' => $recoverableInstallPath,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp(
            strtolower((string) ($a['name'] ?? '')),
            strtolower((string) ($b['name'] ?? ''))
        ));

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByRouteKey(string $routeKey): ?array
    {
        $routeKey = strtolower(trim($routeKey));
        foreach ($this->list() as $item) {
            if (strtolower((string) ($item['route_key'] ?? '')) === $routeKey) {
                return $item;
            }
        }

        return null;
    }

    private function recoverableInstallPathForManifest(string $moduleKey): ?string
    {
        $installPath = $this->moduleInstallPath($moduleKey);
        if (! is_dir($installPath) && ! is_link($installPath)) {
            return null;
        }

        try {
            $existingManifest = $this->manifestParser->parseFromDirectory($installPath);
        } catch (\Throwable) {
            return null;
        }

        return strtolower($existingManifest->id) === strtolower($moduleKey) ? $installPath : null;
    }

    private function moduleInstallPath(string $moduleKey): string
    {
        $root = (string) config('modules.modules_root', base_path('modules'));

        return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->moduleDirName($moduleKey);
    }

    private function moduleDirName(string $moduleKey): string
    {
        return str_replace('/', '--', strtolower($moduleKey));
    }
}
