<?php

namespace TestoCms\Booking;

use App\Modules\Extensibility\Registry\ModuleWidgetRegistry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use TestoCms\Booking\Console\BookingMaintenanceCommand;
use TestoCms\Booking\Services\BookingWidgetCatalogService;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $base = dirname(__DIR__);

        $this->loadRoutesFrom($base.'/routes/admin.php');
        $this->loadViewsFrom($base.'/resources/views', 'booking-module');

        if ($this->app->runningInConsole()) {
            $this->commands([BookingMaintenanceCommand::class]);
        }

        $this->registerWidgets();
    }

    private function registerWidgets(): void
    {
        if (! Schema::hasTable('booking_services') || ! Schema::hasTable('booking_service_translations')) {
            return;
        }

        app(ModuleWidgetRegistry::class)->registerMany('testocms/booking', app(BookingWidgetCatalogService::class)->definitions());
    }
}
