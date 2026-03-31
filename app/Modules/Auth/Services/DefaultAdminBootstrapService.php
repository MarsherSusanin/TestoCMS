<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DefaultAdminBootstrapService
{
    private bool $checked = false;

    public function __construct(
        private readonly AdminProvisionerService $adminProvisioner,
    ) {}

    public function ensureDefaultAdminExists(): void
    {
        if ($this->checked) {
            return;
        }

        $this->checked = true;

        try {
            if (! $this->canBootstrap()) {
                return;
            }

            if (User::query()->exists()) {
                return;
            }

            app(RolesAndPermissionsSeeder::class)->run();
            $this->adminProvisioner->provisionFromEnvironment(false);
        } catch (\Throwable $e) {
            Log::warning('Default admin bootstrap skipped', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function canBootstrap(): bool
    {
        foreach (['users', 'roles', 'permissions', 'model_has_roles'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return trim((string) env('CMS_ADMIN_EMAIL', '')) !== ''
            && (string) env('CMS_ADMIN_PASSWORD', '') !== '';
    }
}
