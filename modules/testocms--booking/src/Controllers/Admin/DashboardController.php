<?php

namespace TestoCms\Booking\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use TestoCms\Booking\Controllers\Admin\Concerns\EnsuresBookingPermissions;
use TestoCms\Booking\Models\BookingBooking;
use TestoCms\Booking\Models\BookingLocation;
use TestoCms\Booking\Models\BookingResource;
use TestoCms\Booking\Models\BookingService;

class DashboardController extends Controller
{
    use EnsuresBookingPermissions;

    public function __invoke(Request $request): View
    {
        $this->ensureBookingRead($request);

        return view('booking-module::admin.dashboard', [
            'stats' => [
                'services' => BookingService::query()->count(),
                'active_services' => BookingService::query()->where('is_active', true)->count(),
                'resources' => BookingResource::query()->count(),
                'locations' => BookingLocation::query()->count(),
                'requested' => BookingBooking::query()->where('status', 'requested')->count(),
                'upcoming' => BookingBooking::query()->whereIn('status', ['requested', 'confirmed'])->where('starts_at', '>=', now())->count(),
            ],
            'recentBookings' => BookingBooking::query()
                ->with(['service.translations', 'location', 'resource'])
                ->latest('starts_at')
                ->limit(12)
                ->get(),
        ]);
    }
}
