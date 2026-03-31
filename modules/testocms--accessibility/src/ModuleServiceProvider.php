<?php

namespace TestoCms\Accessibility;

use App\Modules\Extensibility\Registry\PublicChromeRegistry;
use Illuminate\Support\ServiceProvider;
use TestoCms\Accessibility\Services\AccessibilityUiService;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccessibilityUiService::class, AccessibilityUiService::class);
    }

    public function boot(): void
    {
        $base = dirname(__DIR__);

        $this->loadViewsFrom($base.'/resources/views', 'accessibility-module');
        $this->registerPublicChrome();
    }

    private function registerPublicChrome(): void
    {
        $views = [
            'head_bootstrap' => 'head-bootstrap',
            'head' => 'head',
            'body_start' => 'body-start',
            'header_actions' => 'header-actions',
        ];

        foreach ($views as $zone => $viewName) {
            app(PublicChromeRegistry::class)->registerRenderable(
                $zone,
                'module:testocms/accessibility:'.$zone,
                function (array $context) use ($viewName): string {
                    return view(
                        'accessibility-module::public.partials.'.$viewName,
                        app(AccessibilityUiService::class)->viewData($context)
                    )->render();
                }
            );
        }
    }
}
