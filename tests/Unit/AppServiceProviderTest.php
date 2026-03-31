<?php

namespace Tests\Unit;

use App\Modules\Extensibility\Services\ModuleRuntimeService;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Mockery;
use Psr\Log\NullLogger;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_module_runtime_bootstrap_is_deferred_until_application_boot(): void
    {
        $originalFacadeApplication = Facade::getFacadeApplication();
        $app = new Application(base_path());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $app->instance('log', new NullLogger);

        try {
            $provider = new AppServiceProvider($app);
            $provider->register();

            $bootstrapped = false;
            $runtime = Mockery::mock(ModuleRuntimeService::class);
            $runtime->shouldReceive('registerEnabledProvidersFromCache')
                ->once()
                ->with($app)
                ->andReturnUsing(function () use (&$bootstrapped): void {
                    $bootstrapped = true;
                });

            $app->instance(ModuleRuntimeService::class, $runtime);

            $this->assertFalse($bootstrapped);

            $app->boot();

            $this->assertTrue($bootstrapped);
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($originalFacadeApplication);
        }
    }
}
