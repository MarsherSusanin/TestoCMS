<?php

namespace TestoCms\Booking\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ops\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use TestoCms\Booking\Controllers\Admin\Concerns\EnsuresBookingPermissions;
use TestoCms\Booking\Models\BookingBooking;
use TestoCms\Booking\Models\BookingLocation;
use TestoCms\Booking\Models\BookingResource;
use TestoCms\Booking\Models\BookingService;
use TestoCms\Booking\Services\BookingBookingWorkflowService;
use TestoCms\Booking\Services\BookingReservationService;

class BookingController extends Controller
{
    use EnsuresBookingPermissions;

    public function __construct(
        private readonly BookingReservationService $reservations,
        private readonly BookingBookingWorkflowService $workflow,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureBookingRead($request);
        $services = BookingService::query()->with('translations')->orderByDesc('is_active')->orderByDesc('updated_at')->get();

        $query = BookingBooking::query()
            ->with(['service.translations', 'location', 'resource'])
            ->latest('starts_at');

        foreach (['status', 'service_id', 'location_id', 'resource_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('date_from')) {
            $query->where('starts_at', '>=', $request->date('date_from')?->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('starts_at', '<=', $request->date('date_to')?->endOfDay());
        }

        $bookings = $query->paginate(30)->withQueryString();
        $selectedBooking = null;
        $selectedBookingId = $request->integer('booking_id');
        if ($selectedBookingId > 0) {
            $selectedBooking = $bookings->getCollection()->firstWhere('id', $selectedBookingId);
        }
        if (! $selectedBooking instanceof BookingBooking) {
            $selectedBooking = $bookings->getCollection()->first();
        }
        if ($selectedBooking instanceof BookingBooking) {
            $selectedBooking->loadMissing(['service.resources', 'service.location', 'service.translations', 'slotOccurrence']);
        }

        [$createService, $createDate, $createResourceId, $createSlots] = $this->buildCreateFormState($request, $services);
        [$rescheduleDate, $rescheduleResourceId, $rescheduleSlots] = $this->buildRescheduleFormState($request, $selectedBooking);

        return view('booking-module::admin.bookings.index', [
            'bookings' => $bookings,
            'selectedBooking' => $selectedBooking,
            'services' => $services,
            'locations' => BookingLocation::query()->orderBy('name')->get(),
            'resources' => BookingResource::query()->orderBy('name')->get(),
            'createService' => $createService,
            'createDate' => $createDate,
            'createResourceId' => $createResourceId,
            'createSlots' => $createSlots,
            'rescheduleDate' => $rescheduleDate,
            'rescheduleResourceId' => $rescheduleResourceId,
            'rescheduleSlots' => $rescheduleSlots,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureBookingWrite($request);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:booking_services,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'slot_id' => ['required', 'integer', 'exists:booking_slot_occurrences,id'],
            'resource_id' => ['nullable', 'integer', 'exists:booking_resources,id'],
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'customer_comment' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'invoice_status' => ['nullable', Rule::in(['pending', 'issued', 'paid', 'cancelled'])],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'pending', 'paid', 'refunded', 'failed'])],
        ]);

        $service = BookingService::query()->with(['resources', 'location'])->findOrFail((int) $validated['service_id']);
        $booking = $this->reservations->reserveForPublicSelection(
            $service,
            (int) $validated['slot_id'],
            isset($validated['resource_id']) ? (int) $validated['resource_id'] : null,
            [
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_comment' => $validated['customer_comment'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'invoice_status' => $validated['invoice_status'] ?? 'pending',
                'payment_status' => $validated['payment_status'] ?? 'unpaid',
            ],
            'admin'
        );

        $this->audit->log('booking.booking.create.web', $booking, [
            'service_id' => $service->id,
            'slot_id' => (int) $validated['slot_id'],
            'resource_id' => $booking->resource_id,
            'source' => 'admin',
        ], $request);

        return redirect()->route('booking.admin.bookings.index', [
            'booking_id' => $booking->id,
            'create_service_id' => $service->id,
            'create_date' => (string) $validated['date'],
            'create_resource_id' => $validated['resource_id'] ?? null,
        ])->with('status', 'Бронь создана вручную.');
    }

    public function reschedule(Request $request, BookingBooking $booking): RedirectResponse
    {
        $this->ensureBookingManage($request);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'slot_id' => ['required', 'integer', 'exists:booking_slot_occurrences,id'],
            'resource_id' => ['nullable', 'integer', 'exists:booking_resources,id'],
        ]);

        $booking = $this->workflow->rescheduleFromSelection(
            $booking,
            (int) $validated['slot_id'],
            isset($validated['resource_id']) ? (int) $validated['resource_id'] : null,
        );

        $this->audit->log('booking.booking.reschedule.web', $booking, [
            'slot_id' => (int) $validated['slot_id'],
            'resource_id' => $validated['resource_id'] ?? null,
        ], $request);

        return redirect()->route('booking.admin.bookings.index', [
            'booking_id' => $booking->id,
            'reschedule_date' => (string) $validated['date'],
            'reschedule_resource_id' => $validated['resource_id'] ?? null,
        ])->with('status', 'Бронь перенесена.');
    }

    public function update(Request $request, BookingBooking $booking): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $validated = $request->validate([
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'invoice_status' => ['required', Rule::in(['pending', 'issued', 'paid', 'cancelled'])],
            'payment_status' => ['required', Rule::in(['unpaid', 'pending', 'paid', 'refunded', 'failed'])],
        ]);

        $booking = $this->workflow->updateOperationalFields($booking, $validated);
        $this->audit->log('booking.booking.update.web', $booking, [
            'invoice_status' => $booking->invoice_status,
            'payment_status' => $booking->payment_status,
        ], $request);

        return back()->with('status', 'Карточка брони обновлена.');
    }

    public function confirm(Request $request, BookingBooking $booking): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $booking = $this->workflow->confirm($booking);
        $this->audit->log('booking.booking.confirm.web', $booking, [], $request);

        return back()->with('status', 'Бронирование подтверждено.');
    }

    public function cancel(Request $request, BookingBooking $booking): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $booking = $this->workflow->cancel($booking);
        $this->audit->log('booking.booking.cancel.web', $booking, [], $request);

        return back()->with('status', 'Бронирование отменено.');
    }

    public function complete(Request $request, BookingBooking $booking): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $booking = $this->workflow->complete($booking);
        $this->audit->log('booking.booking.complete.web', $booking, [], $request);

        return back()->with('status', 'Бронирование завершено.');
    }

    public function noShow(Request $request, BookingBooking $booking): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $booking = $this->workflow->noShow($booking);
        $this->audit->log('booking.booking.no_show.web', $booking, [], $request);

        return back()->with('status', 'Отмечено как no-show.');
    }

    public function updateInvoiceStatus(Request $request, BookingBooking $booking): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $validated = $request->validate([
            'invoice_status' => ['required', Rule::in(['pending', 'issued', 'paid', 'cancelled'])],
        ]);
        $booking = $this->workflow->updateInvoiceStatus($booking, (string) $validated['invoice_status']);
        $this->audit->log('booking.booking.invoice_status.web', $booking, ['invoice_status' => $booking->invoice_status], $request);

        return back()->with('status', 'Статус счёта обновлён.');
    }

    public function updatePaymentStatus(Request $request, BookingBooking $booking): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $validated = $request->validate([
            'payment_status' => ['required', Rule::in(['unpaid', 'pending', 'paid', 'refunded', 'failed'])],
        ]);
        $booking = $this->workflow->updatePaymentStatus($booking, (string) $validated['payment_status']);
        $this->audit->log('booking.booking.payment_status.web', $booking, ['payment_status' => $booking->payment_status], $request);

        return back()->with('status', 'Статус оплаты обновлён.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BookingService>  $services
     * @return array{0: ?BookingService, 1: string, 2: ?int, 3: array<string, mixed>}
     */
    private function buildCreateFormState(Request $request, $services): array
    {
        $createServiceId = $request->integer('create_service_id') ?: $services->first()?->id;
        $createService = $createServiceId
            ? BookingService::query()->with(['translations', 'resources', 'location'])->find($createServiceId)
            : null;

        $timezone = $createService?->location?->timezone ?: config('app.timezone', 'UTC');
        $createDate = (string) ($request->input('create_date') ?: CarbonImmutable::now($timezone)->toDateString());
        $createResourceId = $request->filled('create_resource_id') ? $request->integer('create_resource_id') : null;
        $createSlots = [
            'mode' => 'auto_assign',
            'requires_resource' => false,
            'resources' => [],
            'slots' => [],
        ];

        if ($createService) {
            $createSlots = $this->reservations->availableSlots($createService, $createDate, $createResourceId);
        }

        return [$createService, $createDate, $createResourceId, $createSlots];
    }

    /**
     * @return array{0: ?string, 1: ?int, 2: array<string, mixed>}
     */
    private function buildRescheduleFormState(Request $request, ?BookingBooking $selectedBooking): array
    {
        if (! $selectedBooking instanceof BookingBooking || ! $selectedBooking->service) {
            return [null, null, [
                'mode' => 'auto_assign',
                'requires_resource' => false,
                'resources' => [],
                'slots' => [],
            ]];
        }

        $timezone = $selectedBooking->service->location?->timezone ?: config('app.timezone', 'UTC');
        $rescheduleDate = (string) ($request->input('reschedule_date') ?: $selectedBooking->starts_at?->timezone($timezone)->toDateString());
        $rescheduleResourceId = $request->filled('reschedule_resource_id')
            ? $request->integer('reschedule_resource_id')
            : ($selectedBooking->service->usesResourceChoiceMode() ? $selectedBooking->resource_id : null);
        $rescheduleSlots = $this->reservations->availableSlots($selectedBooking->service, $rescheduleDate, $rescheduleResourceId);

        return [$rescheduleDate, $rescheduleResourceId, $rescheduleSlots];
    }
}
