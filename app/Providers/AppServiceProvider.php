<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Modules\Extensibility\Registry\AdminNavigationRegistry;
use App\Modules\Extensibility\Registry\ModuleWidgetRegistry;
use App\Modules\Extensibility\Registry\PublicChromeRegistry;
use App\Modules\Extensibility\Services\BundledModuleCatalogService;
use App\Modules\Extensibility\Services\ModuleCacheService;
use App\Modules\Extensibility\Services\EnabledModulePublicRoutesLoader;
use App\Modules\Extensibility\Services\ModuleInstallerService;
use App\Modules\Extensibility\Services\ModuleManagerService;
use App\Modules\Extensibility\Services\ModuleManifestParserService;
use App\Modules\Extensibility\Services\ModuleRuntimeService;
use App\Modules\Extensibility\Services\ModuleSecuritySyncService;
use App\Policies\AssetPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\PagePolicy;
use App\Policies\PostPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AdminNavigationRegistry::class, AdminNavigationRegistry::class);
        $this->app->singleton(ModuleWidgetRegistry::class, ModuleWidgetRegistry::class);
        $this->app->singleton(PublicChromeRegistry::class, PublicChromeRegistry::class);
        $this->app->singleton(ModuleManifestParserService::class, ModuleManifestParserService::class);
        $this->app->singleton(ModuleCacheService::class, ModuleCacheService::class);
        $this->app->singleton(BundledModuleCatalogService::class, BundledModuleCatalogService::class);
        $this->app->singleton(EnabledModulePublicRoutesLoader::class, EnabledModulePublicRoutesLoader::class);
        $this->app->singleton(ModuleInstallerService::class, ModuleInstallerService::class);
        $this->app->singleton(ModuleSecuritySyncService::class, ModuleSecuritySyncService::class);
        $this->app->singleton(ModuleRuntimeService::class, ModuleRuntimeService::class);
        $this->app->singleton(ModuleManagerService::class, ModuleManagerService::class);

        $this->app->booting(function (): void {
            try {
                $this->app->make(ModuleRuntimeService::class)->registerEnabledProvidersFromCache($this->app);
            } catch (\Throwable $e) {
                Log::warning('Module runtime bootstrap skipped', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
    }
}
