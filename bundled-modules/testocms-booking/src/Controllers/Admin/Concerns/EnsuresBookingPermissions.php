<?php

namespace TestoCms\Booking\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

trait EnsuresBookingPermissions
{
    protected function ensureBookingRead(Request $request): void
    {
        $this->ensureBookingPermission($request, ['booking:read', 'booking:write', 'booking:manage', 'booking:settings']);
    }

    protected function ensureBookingWrite(Request $request): void
    {
        $this->ensureBookingPermission($request, ['booking:write', 'booking:manage', 'booking:settings']);
    }

    protected function ensureBookingManage(Request $request): void
    {
        $this->ensureBookingPermission($request, ['booking:manage', 'booking:settings']);
    }

    protected function ensureBookingSettings(Request $request): void
    {
        $this->ensureBookingPermission($request, ['booking:settings']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function ensureBookingPermission(Request $request, array $permissions): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403);
    }

    /**
     * @return array<int, string>
     */
    protected function supportedLocales(): array
    {
        return array_values(array_map(static fn (mixed $locale): string => strtolower((string) $locale), (array) config('cms.supported_locales', ['ru', 'en'])));
    }
}
