<?php

namespace Database\Seeders;

use App\Modules\Auth\Services\AdminProvisionerService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (trim((string) env('CMS_ADMIN_EMAIL', '')) !== '' && (string) env('CMS_ADMIN_PASSWORD', '') !== '') {
            app(AdminProvisionerService::class)->provisionFromEnvironment();
        }

        $this->call(DemoContentSeeder::class);
    }
}
