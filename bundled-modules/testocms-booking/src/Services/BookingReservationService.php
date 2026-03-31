<?php

namespace TestoCms\Booking\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use TestoCms\Booking\Models\BookingBooking;
use TestoCms\Booking\Models\BookingResource;
use TestoCms\Booking\Models\BookingService;
use TestoCms\Booking\Models\BookingSlotOccurrence;

class BookingReservationService
{
    public function __construct(
        private readonly BookingSettingsService $settings,
        private readonly BookingWebhookService $webhooks,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function reserve(BookingService $service, BookingSlotOccurrence $slot, array $payload, string $source = 'public_page'): BookingBooking
    {
        return DB::transaction(function () use ($service, $slot, $payload, $source): BookingBooking {
            /** @var BookingSlotOccurrence|null $lockedSlot */
            $lockedSlot = BookingSlotOccurrence::query()->whereKey($slot->id)->lockForUpdate()->first();
            if (! $lockedSlot) {
                throw new RuntimeException('Выбранный слот больше недоступен.');
            }

            return $this->reserveOnLockedSlot($service, $lockedSlot, $payload, $source);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function reserveForPublicSelection(
        BookingService $service,
        int $slotId,
        ?int $resourceId,
        array $payload,
        string $source = 'public_page'
    ): BookingBooking {
        $mode = $this->resourceSelectionMode($service);

        return DB::transaction(function () use ($service, $slotId, $resourceId, $payload, $source, $mode): BookingBooking {
            $referenceSlot = BookingSlotOccurrence::query()
                ->where('service_id', $service->id)
                ->whereKey($slotId)
                ->lockForUpdate()
                ->first();

            if (! $referenceSlot) {
                throw new RuntimeException('Выбранный слот больше недоступен.');
            }

            if ($mode === 'choose_resource') {
                if (! $resourceId) {
                    throw new RuntimeException('Сначала выберите ресурс.');
                }

                if ((int) $referenceSlot->resource_id !== $resourceId) {
                    throw new RuntimeException('Выбранный слот не относится к указанному ресурсу.');
                }

                return $this->reserveOnLockedSlot($service, $referenceSlot, $payload + ['resource_id' => $resourceId], $source);
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

            return $this->reserveOnLockedSlot($service, $candidate, $payload, $source);
        });
    }

    /**
     * @return array{mode: string, requires_resource: bool, resources: array<int, array<string, mixed>>, slots: array<int, array<string, mixed>>}
     */
    public function availableSlots(BookingService $service, string $date, ?int $resourceId = null): array
    {
        $timezone = $service->location?->timezone ?: config('app.timezone', 'UTC');
        $dayStart = CarbonImmutable::parse($date, $timezone)->startOfDay()->setTimezone('UTC');
        $dayEnd = $dayStart->endOfDay();
        $mode = $this->resourceSelectionMode($service);
        $resources = $this->activeResources($service);

        $query = BookingSlotOccurrence::query()
            ->where('service_id', $service->id)
            ->whereBetween('starts_at', [$dayStart->toDateTimeString(), $dayEnd->toDateTimeString()])
            ->where('status', 'open')
            ->orderBy('starts_at');

        if ($mode === 'choose_resource' && ! $resourceId) {
            return [
                'mode' => $mode,
                'requires_resource' => true,
                'resources' => $resources->map(fn (BookingResource $resource): array => [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'resource_type' => $resource->resource_type,
                ])->values()->all(),
                'slots' => [],
            ];
        }

        if ($resourceId) {
            $query->where('resource_id', $resourceId);
        }

        $slots = $query->get();

        return [
            'mode' => $mode,
            'requires_resource' => $mode === 'choose_resource',
            'resources' => $resources->map(fn (BookingResource $resource): array => [
                'id' => $resource->id,
                'name' => $resource->name,
                'resource_type' => $resource->resource_type,
            ])->values()->all(),
            'slots' => $mode === 'choose_resource'
                ? $this->individualSlots($slots, $timezone)
                : $this->aggregatedSlots($slots, $timezone),
        ];
    }

    /**
     * @param  Collection<int, BookingSlotOccurrence>  $slots
     * @return array<int, array<string, mixed>>
     */
    private function individualSlots(Collection $slots, string $timezone): array
    {
        return $slots
            ->map(function (BookingSlotOccurrence $slot) use ($timezone): array {
                $available = $this->availableCapacity($slot);

                return [
                    'id' => $slot->id,
                    'starts_at' => $slot->starts_at?->setTimezone($timezone)?->toIso8601String(),
                    'ends_at' => $slot->ends_at?->setTimezone($timezone)?->toIso8601String(),
                    'available' => $available,
                    'available_count' => $available,
                    'resource_id' => $slot->resource_id,
                    'label' => $slot->starts_at?->setTimezone($timezone)?->format('H:i').' - '.$slot->ends_at?->setTimezone($timezone)?->format('H:i'),
                ];
            })
            ->filter(fn (array $slot): bool => (int) ($slot['available_count'] ?? 0) > 0)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, BookingSlotOccurrence>  $slots
     * @return array<int, array<string, mixed>>
     */
    private function aggregatedSlots(Collection $slots, string $timezone): array
    {
        return $slots
            ->groupBy(fn (BookingSlotOccurrence $slot): string => ($slot->starts_at?->toDateTimeString() ?? '').'|'.($slot->ends_at?->toDateTimeString() ?? ''))
            ->map(function (Collection $group) use ($timezone): ?array {
                /** @var BookingSlotOccurrence|null $representative */
                $representative = $group->sortBy('id')->first();
                if (! $representative instanceof BookingSlotOccurrence) {
                    return null;
                }

                $availableCount = $group->sum(fn (BookingSlotOccurrence $slot): int => $this->availableCapacity($slot));
                if ($availableCount < 1) {
                    return null;
                }

                return [
                    'id' => $representative->id,
                    'starts_at' => $representative->starts_at?->setTimezone($timezone)?->toIso8601String(),
                    'ends_at' => $representative->ends_at?->setTimezone($timezone)?->toIso8601String(),
                    'available' => $availableCount,
                    'available_count' => $availableCount,
                    'resource_id' => null,
                    'label' => $representative->starts_at?->setTimezone($timezone)?->format('H:i').' - '.$representative->ends_at?->setTimezone($timezone)?->format('H:i'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function availableCapacity(BookingSlotOccurrence $slot): int
    {
        return max(0, (int) $slot->capacity_total - (int) $slot->reserved_count - (int) $slot->confirmed_count);
    }

    private function resourceSelectionMode(BookingService $service): string
    {
        return $service->usesResourceChoiceMode() ? 'choose_resource' : 'auto_assign';
    }

    /**
     * @return Collection<int, BookingResource>
     */
    private function activeResources(BookingService $service): Collection
    {
        $resources = $service->relationLoaded('resources')
            ? $service->resources
            : $service->resources()->get();

        return $resources
            ->filter(fn (BookingResource $resource): bool => (bool) $resource->is_active)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reserveOnLockedSlot(BookingService $service, BookingSlotOccurrence $lockedSlot, array $payload, string $source): BookingBooking
    {
        if ($lockedSlot->status !== 'open') {
            throw new RuntimeException('Слот закрыт для бронирования.');
        }

        $available = $this->availableCapacity($lockedSlot);
        if ($available < 1) {
            throw new RuntimeException('Слот уже занят.');
        }

        $isInstant = (string) $service->confirmation_mode === 'instant';
        $booking = BookingBooking::query()->create([
            'service_id' => $service->id,
            'location_id' => $lockedSlot->location_id ?: $service->location_id,
            'resource_id' => $lockedSlot->resource_id,
            'slot_occurrence_id' => $lockedSlot->id,
            'starts_at' => $lockedSlot->starts_at,
            'ends_at' => $lockedSlot->ends_at,
            'customer_name' => (string) ($payload['customer_name'] ?? ''),
            'customer_email' => $payload['customer_email'] ?? null,
            'customer_phone' => $payload['customer_phone'] ?? null,
            'customer_comment' => $payload['customer_comment'] ?? null,
            'status' => $isInstant ? 'confirmed' : 'requested',
            'invoice_status' => (string) ($payload['invoice_status'] ?? 'pending'),
            'payment_status' => (string) ($payload['payment_status'] ?? 'unpaid'),
            'source' => $source,
            'internal_notes' => $payload['internal_notes'] ?? null,
            'hold_expires_at' => $isInstant ? null : now()->addMinutes((int) ($this->settings->resolved()['hold_ttl_minutes'] ?? 120)),
            'confirmed_at' => $isInstant ? now() : null,
            'meta' => ['ip' => request()?->ip()],
        ]);

        if ($isInstant) {
            $lockedSlot->confirmed_count = (int) $lockedSlot->confirmed_count + 1;
        } else {
            $lockedSlot->reserved_count = (int) $lockedSlot->reserved_count + 1;
        }
        $lockedSlot->save();

        $booking->loadMissing(['service.translations', 'slotOccurrence']);
        $this->webhooks->dispatch('booking.created', $this->payloadForWebhook($booking));
        if ($isInstant) {
            $this->webhooks->dispatch('booking.confirmed', $this->payloadForWebhook($booking));
        }

        return $booking;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForWebhook(BookingBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'invoice_status' => $booking->invoice_status,
            'payment_status' => $booking->payment_status,
            'starts_at' => $booking->starts_at?->toIso8601String(),
            'ends_at' => $booking->ends_at?->toIso8601String(),
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'service_id' => $booking->service_id,
            'slot_occurrence_id' => $booking->slot_occurrence_id,
        ];
    }
}
