<?php

namespace App\Providers;

use App\Models\CategoryTranslation;
use App\Models\PageTranslation;
use App\Models\PostTranslation;
use App\Modules\Auth\Services\DefaultAdminBootstrapService;
use App\Modules\Auth\Services\AdminProvisionerService;
use App\Modules\Content\Services\BlockRendererService;
use App\Modules\Content\Contracts\PageContentServiceContract;
use App\Modules\Content\Contracts\PostContentServiceContract;
use App\Modules\Content\Contracts\PageWorkflowServiceContract;
use App\Modules\Content\Contracts\PostWorkflowServiceContract;
use App\Modules\Content\Services\PageContentService;
use App\Modules\Content\Services\PostContentService;
use App\Modules\Content\Services\PageWorkflowService;
use App\Modules\Content\Services\PostWorkflowService;
use App\Modules\Core\Contracts\BlockRendererContract;
use App\Modules\Core\Contracts\ContentRevisionServiceContract;
use App\Modules\Core\Contracts\SiteChromeEditorServiceContract;
use App\Modules\Core\Contracts\SanitizerContract;
use App\Modules\Core\Contracts\SeoResolverContract;
use App\Modules\Core\Contracts\ThemeEditorServiceContract;
use App\Modules\Core\Services\ChromeLinkTargetCatalogService;
use App\Modules\Core\Services\AdminShellViewModelFactory;
use App\Modules\Core\Services\AdminSidebarIconRegistry;
use App\Modules\Core\Services\HtmlSanitizerService;
use App\Modules\Core\Services\LocalBaselineBootstrapService;
use App\Modules\Core\Services\RevisionService;
use App\Modules\Core\Services\CmsLayoutViewModelFactory;
use App\Modules\Core\Services\ResolvedChromeViewModelFactory;
use App\Modules\Core\Services\ResolvedThemeViewModelFactory;
use App\Modules\Core\Services\SiteChromeSettingsService;
use App\Modules\Core\Services\SiteChromeEditorService;
use App\Modules\LLM\Providers\AnthropicProvider;
use App\Modules\LLM\Providers\OpenAiProvider;
use App\Modules\LLM\Services\LlmGatewayService;
use App\Modules\SEO\Services\SeoResolverService;
use App\Modules\Setup\Services\SetupFinalizationService;
use App\Modules\Core\Services\ThemeCssRenderer;
use App\Modules\Core\Services\ThemeEditorService;
use App\Modules\Core\Services\ThemeSettingsService;
use App\Observers\TranslationSlugObserver;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(SanitizerContract::class, HtmlSanitizerService::class);
        $this->app->bind(BlockRendererContract::class, BlockRendererService::class);
        $this->app->bind(SeoResolverContract::class, SeoResolverService::class);
        $this->app->bind(ContentRevisionServiceContract::class, RevisionService::class);
        $this->app->bind(PageContentServiceContract::class, PageContentService::class);
        $this->app->bind(PostContentServiceContract::class, PostContentService::class);
        $this->app->bind(PageWorkflowServiceContract::class, PageWorkflowService::class);
        $this->app->bind(PostWorkflowServiceContract::class, PostWorkflowService::class);
        $this->app->bind(ThemeEditorServiceContract::class, ThemeEditorService::class);
        $this->app->bind(SiteChromeEditorServiceContract::class, SiteChromeEditorService::class);
        $this->app->singleton(AdminProvisionerService::class, AdminProvisionerService::class);
        $this->app->singleton(DefaultAdminBootstrapService::class, DefaultAdminBootstrapService::class);
        $this->app->singleton(SetupFinalizationService::class, SetupFinalizationService::class);
        $this->app->singleton(LocalBaselineBootstrapService::class, LocalBaselineBootstrapService::class);
        $this->app->singleton(ThemeSettingsService::class, ThemeSettingsService::class);
        $this->app->singleton(SiteChromeSettingsService::class, SiteChromeSettingsService::class);
        $this->app->singleton(ThemeCssRenderer::class, ThemeCssRenderer::class);
        $this->app->singleton(ChromeLinkTargetCatalogService::class, ChromeLinkTargetCatalogService::class);
        $this->app->singleton(AdminSidebarIconRegistry::class, AdminSidebarIconRegistry::class);
        $this->app->bind(ResolvedThemeViewModelFactory::class, ResolvedThemeViewModelFactory::class);
        $this->app->bind(ResolvedChromeViewModelFactory::class, ResolvedChromeViewModelFactory::class);
        $this->app->bind(CmsLayoutViewModelFactory::class, CmsLayoutViewModelFactory::class);
        $this->app->bind(AdminShellViewModelFactory::class, AdminShellViewModelFactory::class);

        $this->app->singleton(OpenAiProvider::class, OpenAiProvider::class);
        $this->app->singleton(AnthropicProvider::class, AnthropicProvider::class);

        $this->app->singleton(LlmGatewayService::class, function ($app): LlmGatewayService {
            return new LlmGatewayService([
                'openai' => $app->make(OpenAiProvider::class),
                'anthropic' => $app->make(AnthropicProvider::class),
            ]);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        PostTranslation::observe(TranslationSlugObserver::class);
        PageTranslation::observe(TranslationSlugObserver::class);
        CategoryTranslation::observe(TranslationSlugObserver::class);

        View::composer('cms.layout', function ($view): void {
            $view->with('siteTheme', $this->app->make(ThemeSettingsService::class)->resolvedTheme());
            $view->with('siteChrome', $this->app->make(SiteChromeSettingsService::class)->resolvedChrome());
            $view->with('cmsLayout', $this->app->make(CmsLayoutViewModelFactory::class)->build($view->getData()));
        });

        View::composer('admin.layout', function ($view): void {
            $view->with('adminShell', $this->app->make(AdminShellViewModelFactory::class)->build(auth()->user()));
        });
    }
}
