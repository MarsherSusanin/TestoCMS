<?php

namespace TestoCms\Booking\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use TestoCms\Booking\Controllers\Admin\Concerns\EnsuresBookingPermissions;
use TestoCms\Booking\Models\BookingBooking;
use TestoCms\Booking\Models\BookingLocation;
use TestoCms\Booking\Models\BookingResource;
use TestoCms\Booking\Models\BookingService;

class CalendarController extends Controller
{
    use EnsuresBookingPermissions;

    public function index(Request $request): View
    {
        $this->ensureBookingRead($request);

        $viewMode = $request->input('view') === 'day' ? 'day' : 'week';
        $offset = $request->integer('offset', 0);
        $baseDate = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->startOfDay()
            : now()->startOfDay();
        $start = $baseDate->copy()->addDays($offset);
        $end = $viewMode === 'day'
            ? $start->copy()->endOfDay()
            : $start->copy()->addDays(6)->endOfDay();

        $query = BookingBooking::query()
            ->with(['service.translations', 'location', 'resource'])
            ->whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at');

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->integer('service_id'));
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }
        if ($request->filled('resource_id')) {
            $query->where('resource_id', $request->integer('resource_id'));
        }

        $bookings = $query->get()->groupBy(fn ($booking) => $booking->starts_at?->format('Y-m-d'));

        return view('booking-module::admin.calendar.index', [
            'start' => $start,
            'end' => $end,
            'viewMode' => $viewMode,
            'bookings' => $bookings,
            'services' => BookingService::query()->with('translations')->orderByDesc('is_active')->orderByDesc('updated_at')->get(),
            'locations' => BookingLocation::query()->orderBy('name')->get(),
            'resources' => BookingResource::query()->orderBy('name')->get(),
        ]);
    }
}
