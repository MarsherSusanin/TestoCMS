<?php

namespace TestoCms\Booking\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use TestoCms\Booking\Controllers\Admin\Concerns\EnsuresBookingPermissions;
use TestoCms\Booking\Models\BookingLocation;

class LocationController extends Controller
{
    use EnsuresBookingPermissions;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->ensureBookingRead($request);

        return view('booking-module::admin.locations.index', [
            'locations' => BookingLocation::query()->latest('id')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureBookingWrite($request);

        return view('booking-module::admin.locations.form', [
            'location' => new BookingLocation(['timezone' => config('app.timezone', 'UTC'), 'is_active' => true]),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        $location = BookingLocation::query()->create($this->validated($request));
        $this->audit->log('booking.location.create.web', $location, [], $request);

        return redirect()->route('booking.admin.locations.index')->with('status', 'Локация создана.');
    }

    public function edit(Request $request, BookingLocation $location): View
    {
        $this->ensureBookingWrite($request);

        return view('booking-module::admin.locations.form', [
            'location' => $location,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, BookingLocation $location): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        $location->fill($this->validated($request));
        $location->save();
        $this->audit->log('booking.location.update.web', $location, [], $request);

        return redirect()->route('booking.admin.locations.index')->with('status', 'Локация обновлена.');
    }

    public function destroy(Request $request, BookingLocation $location): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $id = $location->id;
        $location->delete();
        $this->audit->log('booking.location.delete.web', null, ['location_id' => $id], $request);

        return redirect()->route('booking.admin.locations.index')->with('status', 'Локация удалена.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'timezone' => ['required', 'timezone'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
