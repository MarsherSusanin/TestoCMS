<?php

namespace TestoCms\Booking\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use TestoCms\Booking\Models\BookingBooking;
use TestoCms\Booking\Models\BookingService;
use TestoCms\Booking\Models\BookingSlotOccurrence;

class BookingBookingWorkflowService
{
    public function __construct(
        private readonly BookingWebhookService $webhooks,
        private readonly BookingSettingsService $settings,
    )
    {
    }

    public function confirm(BookingBooking $booking): BookingBooking
    {
        return DB::transaction(function () use ($booking): BookingBooking {
            $booking = BookingBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->status !== 'requested') {
                throw new RuntimeException('Подтвердить можно только заявку в статусе requested.');
            }

            $slot = $booking->slotOccurrence()->lockForUpdate()->first();
            if ($slot instanceof BookingSlotOccurrence) {
                $slot->reserved_count = max(0, (int) $slot->reserved_count - 1);
                $slot->confirmed_count = (int) $slot->confirmed_count + 1;
                $slot->save();
            }

            $booking->forceFill([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'hold_expires_at' => null,
            ])->save();

            $this->webhooks->dispatch('booking.confirmed', $this->payload($booking));

            return $booking;
        });
    }

    public function cancel(BookingBooking $booking): BookingBooking
    {
        return DB::transaction(function () use ($booking): BookingBooking {
            $booking = BookingBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (in_array($booking->status, ['cancelled', 'completed', 'no_show'], true)) {
                return $booking;
            }

            $slot = $booking->slotOccurrence()->lockForUpdate()->first();
            if ($slot instanceof BookingSlotOccurrence) {
                if ($booking->status === 'requested') {
                    $slot->reserved_count = max(0, (int) $slot->reserved_count - 1);
                } elseif ($booking->status === 'confirmed') {
                    $slot->confirmed_count = max(0, (int) $slot->confirmed_count - 1);
                }
                $slot->save();
            }

            $booking->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'hold_expires_at' => null,
            ])->save();

            $this->webhooks->dispatch('booking.cancelled', $this->payload($booking));

            return $booking;
        });
    }

    public function complete(BookingBooking $booking): BookingBooking
    {
        $booking->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();
        $this->webhooks->dispatch('booking.completed', $this->payload($booking));

        return $booking;
    }

    public function noShow(BookingBooking $booking): BookingBooking
    {
        $booking->forceFill([
            'status' => 'no_show',
            'completed_at' => now(),
        ])->save();
        $this->webhooks->dispatch('booking.no_show', $this->payload($booking));

        return $booking;
    }

    public function rescheduleFromSelection(BookingBooking $booking, int $slotId, ?int $resourceId = null): BookingBooking
    {
        return DB::transaction(function () use ($booking, $slotId, $resourceId): BookingBooking {
            $booking = BookingBooking::query()
                ->with(['service.resources', 'service.location'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($booking->status, ['cancelled', 'completed', 'no_show'], true)) {
                throw new RuntimeException('Переносить можно только активную бронь или заявку.');
            }

            /** @var BookingService $service */
            $service = $booking->service()->with(['resources', 'location'])->firstOrFail();
            $currentSlot = $booking->slotOccurrence()->lockForUpdate()->first();
            if (! $currentSlot instanceof BookingSlotOccurrence) {
                throw new RuntimeException('У текущей брони не найден исходный слот.');
            }

            if ((int) $currentSlot->id === $slotId && (! $resourceId || (int) $booking->resource_id === $resourceId)) {
                return $booking;
            }

            $targetSlot = $this->resolveTargetSlot($service, $slotId, $resourceId);
            $this->releaseCapacity($booking, $currentSlot);
            $this->reserveCapacity($booking, $targetSlot);

            $booking->forceFill([
                'slot_occurrence_id' => $targetSlot->id,
                'location_id' => $targetSlot->location_id ?: $service->location_id,
                'resource_id' => $targetSlot->resource_id,
                'starts_at' => $targetSlot->starts_at,
                'ends_at' => $targetSlot->ends_at,
                'hold_expires_at' => $booking->status === 'requested'
                    ? now()->addMinutes((int) ($this->settings->resolved()['hold_ttl_minutes'] ?? 120))
                    : null,
            ])->save();

            $booking->loadMissing(['service.translations', 'location', 'resource', 'slotOccurrence']);
            $this->webhooks->dispatch('booking.rescheduled', $this->payload($booking));

            return $booking;
        });
    }

    public function updateInvoiceStatus(BookingBooking $booking, string $status): BookingBooking
    {
        $booking->invoice_status = $status;
        $booking->save();

        return $booking;
    }

    public function updatePaymentStatus(BookingBooking $booking, string $status): BookingBooking
    {
        $booking->payment_status = $status;
        $booking->save();

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOperationalFields(BookingBooking $booking, array $payload): BookingBooking
    {
        $booking->forceFill([
            'internal_notes' => blank($payload['internal_notes'] ?? null) ? null : (string) $payload['internal_notes'],
            'invoice_status' => (string) ($payload['invoice_status'] ?? $booking->invoice_status),
            'payment_status' => (string) ($payload['payment_status'] ?? $booking->payment_status),
        ])->save();

        return $booking;
    }

    public function expireRequestedHolds(): int
    {
        $expired = 0;
        BookingBooking::query()
            ->where('status', 'requested')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->chunkById(100, function ($bookings) use (&$expired): void {
                foreach ($bookings as $booking) {
                    $this->cancel($booking);
                    $expired++;
                }
            });

        return $expired;
    }

    private function resolveTargetSlot(BookingService $service, int $slotId, ?int $resourceId): BookingSlotOccurrence
    {
        $mode = $service->usesResourceChoiceMode() ? 'choose_resource' : 'auto_assign';
        $referenceSlot = BookingSlotOccurrence::query()
            ->where('service_id', $service->id)
            ->whereKey($slotId)
            ->lockForUpdate()
            ->first();

        if (! $referenceSlot instanceof BookingSlotOccurrence) {
            throw new RuntimeException('Выбранный слот больше недоступен.');
        }

        if ($mode === 'choose_resource') {
            if (! $resourceId) {
                throw new RuntimeException('Сначала выберите ресурс.');
            }

            if ((int) $referenceSlot->resource_id !== $resourceId) {
                throw new RuntimeException('Выбранный слот не относится к указанному ресурсу.');
            }

            if ($this->availableCapacity($referenceSlot) < 1) {
                throw new RuntimeException('Выбранный слот уже занят.');
            }

            return $referenceSlot;
        }

        $candidate = BookingSlotOccurrence::query()
            ->where('service_id', $service->id)
            ->where('status', 'open')
            ->where('starts_at', $referenceSlot->starts_at)
            ->where('ends_at', $referenceSlot->ends_at)
            ->when($resourceId, fn ($query) => $query->where('resource_id', $resourceId))
            ->orderByRaw('CASE WHEN resource_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('resource_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->first(fn (BookingSlotOccurrence $slot): bool => $this->availableCapacity($slot) > 0);

        if (! $candidate instanceof BookingSlotOccurrence) {
            throw new RuntimeException('На выбранное время больше нет свободных ресурсов.');
        }

        return $candidate;
    }

    private function releaseCapacity(BookingBooking $booking, BookingSlotOccurrence $slot): void
    {
        if ($booking->status === 'requested') {
            $slot->reserved_count = max(0, (int) $slot->reserved_count - 1);
        } elseif ($booking->status === 'confirmed') {
            $slot->confirmed_count = max(0, (int) $slot->confirmed_count - 1);
        }
        $slot->save();
    }

    private function reserveCapacity(BookingBooking $booking, BookingSlotOccurrence $slot): void
    {
        if ($slot->status !== 'open') {
            throw new RuntimeException('Слот закрыт для бронирования.');
        }

        if ($this->availableCapacity($slot) < 1) {
            throw new RuntimeException('Слот уже занят.');
        }

        if ($booking->status === 'requested') {
            $slot->reserved_count = (int) $slot->reserved_count + 1;
        } elseif ($booking->status === 'confirmed') {
            $slot->confirmed_count = (int) $slot->confirmed_count + 1;
        }
        $slot->save();
    }

    private function availableCapacity(BookingSlotOccurrence $slot): int
    {
        return max(0, (int) $slot->capacity_total - (int) $slot->reserved_count - (int) $slot->confirmed_count);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(BookingBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'invoice_status' => $booking->invoice_status,
            'payment_status' => $booking->payment_status,
            'service_id' => $booking->service_id,
            'slot_occurrence_id' => $booking->slot_occurrence_id,
            'starts_at' => $booking->starts_at?->toIso8601String(),
            'ends_at' => $booking->ends_at?->toIso8601String(),
        ];
    }
}
