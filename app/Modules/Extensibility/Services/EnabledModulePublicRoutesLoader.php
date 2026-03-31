<?php

namespace App\Modules\Extensibility\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;

class EnabledModulePublicRoutesLoader
{
    /**
     * @var array<int, array<string, bool>>
     */
    private static array $loadedPathsByCollection = [];

    public function __construct(private readonly ModuleCacheService $moduleCache)
    {
    }

    public function load(): void
    {
        $loadedAny = false;
        $routeCollection = app('router')->getRoutes();
        $collectionKey = spl_object_id($routeCollection);
        self::$loadedPathsByCollection[$collectionKey] ??= [];

        foreach ($this->moduleCache->loadEnabledModules() as $moduleRow) {
            $path = $this->resolvePublicRoutesPath($moduleRow);
            if ($path !== null && is_file($path) && empty(self::$loadedPathsByCollection[$collectionKey][$path])) {
                require $path;
                self::$loadedPathsByCollection[$collectionKey][$path] = true;
                $loadedAny = true;
            }
        }

        if ($loadedAny) {
            $this->moveCoreLocaleRoutesToEnd($routeCollection);
            $routeCollection->refreshNameLookups();
            $routeCollection->refreshActionLookups();
        }
    }

    public static function reset(): void
    {
        self::$loadedPathsByCollection = [];
    }

    /**
     * @param  array<string, mixed>  $moduleRow
     */
    private function resolvePublicRoutesPath(array $moduleRow): ?string
    {
        $installPath = trim((string) ($moduleRow['install_path'] ?? ''));
        if ($installPath === '' || ! is_dir($installPath)) {
            return null;
        }

        $configured = $moduleRow['metadata']['capabilities']['routes']['web'] ?? 'routes/web.php';
        if ($configured === false || $configured === null) {
            return null;
        }

        $relative = trim((string) $configured, '/\\');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;
    }

    private function moveCoreLocaleRoutesToEnd(RouteCollection $routeCollection): void
    {
        $targets = array_values(array_filter([
            $routeCollection->getByName('site.search'),
            $routeCollection->getByName('site.show'),
        ], static fn (mixed $route): bool => $route instanceof Route));

        if ($targets === []) {
            return;
        }

        $reflection = new \ReflectionClass($routeCollection);
        $allRoutesProperty = $reflection->getProperty('allRoutes');
        $routesProperty = $reflection->getProperty('routes');
        $allRoutesProperty->setAccessible(true);
        $routesProperty->setAccessible(true);

        /** @var array<string, Route> $allRoutes */
        $allRoutes = $allRoutesProperty->getValue($routeCollection);
        /** @var array<string, array<string, Route>> $routesByMethod */
        $routesByMethod = $routesProperty->getValue($routeCollection);

        foreach ($targets as $route) {
            $allKey = $this->allRoutesKey($route);
            unset($allRoutes[$allKey]);

            $domainAndUri = $this->domainAndUriKey($route);
            foreach ($route->methods() as $method) {
                unset($routesByMethod[$method][$domainAndUri]);
            }
        }

        foreach ($targets as $route) {
            $allRoutes[$this->allRoutesKey($route)] = $route;

            $domainAndUri = $this->domainAndUriKey($route);
            foreach ($route->methods() as $method) {
                $routesByMethod[$method][$domainAndUri] = $route;
            }
        }

        $allRoutesProperty->setValue($routeCollection, $allRoutes);
        $routesProperty->setValue($routeCollection, $routesByMethod);
    }

    private function allRoutesKey(Route $route): string
    {
        return implode('|', $route->methods()).$this->domainAndUriKey($route);
    }

    private function domainAndUriKey(Route $route): string
    {
        return $route->getDomain().$route->uri();
    }
}
