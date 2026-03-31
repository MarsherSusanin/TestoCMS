<?php

namespace TestoCms\Booking\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use TestoCms\Booking\Controllers\Admin\Concerns\EnsuresBookingPermissions;
use TestoCms\Booking\Models\BookingLocation;
use TestoCms\Booking\Models\BookingResource;

class ResourceController extends Controller
{
    use EnsuresBookingPermissions;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        $this->ensureBookingRead($request);

        return view('booking-module::admin.resources.index', [
            'resources' => BookingResource::query()->with('location')->latest('id')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureBookingWrite($request);

        return view('booking-module::admin.resources.form', [
            'resource' => new BookingResource(['resource_type' => 'staff', 'capacity' => 1, 'is_active' => true]),
            'locations' => BookingLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        $resource = BookingResource::query()->create($this->validated($request));
        $this->audit->log('booking.resource.create.web', $resource, [], $request);

        return redirect()->route('booking.admin.resources.index')->with('status', 'Ресурс создан.');
    }

    public function edit(Request $request, BookingResource $resource): View
    {
        $this->ensureBookingWrite($request);

        return view('booking-module::admin.resources.form', [
            'resource' => $resource,
            'locations' => BookingLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, BookingResource $resource): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        $resource->fill($this->validated($request));
        $resource->save();
        $this->audit->log('booking.resource.update.web', $resource, [], $request);

        return redirect()->route('booking.admin.resources.index')->with('status', 'Ресурс обновлён.');
    }

    public function destroy(Request $request, BookingResource $resource): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $id = $resource->id;
        $resource->delete();
        $this->audit->log('booking.resource.delete.web', null, ['resource_id' => $id], $request);

        return redirect()->route('booking.admin.resources.index')->with('status', 'Ресурс удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:booking_locations,id'],
            'name' => ['required', 'string', 'max:160'],
            'resource_type' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:5000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
