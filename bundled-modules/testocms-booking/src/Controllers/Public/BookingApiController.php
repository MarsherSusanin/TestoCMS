<?php

namespace TestoCms\Booking\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use TestoCms\Booking\Services\BookingCatalogService;
use TestoCms\Booking\Services\BookingReservationService;

class BookingApiController extends Controller
{
    public function __construct(
        private readonly BookingCatalogService $catalog,
        private readonly BookingReservationService $reservations,
    ) {}

    public function services(string $locale): JsonResponse
    {
        $services = $this->catalog->activeServices($locale)->map(function ($service) use ($locale): array {
            $translation = $service->getRelation('current_translation') ?? $this->catalog->translationForLocale($service, $locale);

            return [
                'id' => $service->id,
                'slug' => (string) ($translation?->slug ?? ''),
                'title' => (string) ($translation?->title ?? ''),
                'summary' => (string) ($translation?->short_description ?? ''),
                'duration_minutes' => (int) $service->duration_minutes,
                'confirmation_mode' => (string) $service->confirmation_mode,
                'resource_selection_mode' => (string) $service->resource_selection_mode,
                'price_amount' => $service->price_amount,
                'price_currency' => (string) $service->price_currency,
                'price_label' => (string) ($service->price_label ?? ''),
            ];
        })->values();

        return response()->json(['data' => $services]);
    }

    public function slots(Request $request, string $locale, string $slug): JsonResponse
    {
        $service = $this->catalog->findActiveServiceBySlug($locale, $slug);
        abort_unless($service, 404);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'resource_id' => ['nullable', 'integer'],
        ]);

        $date = (string) ($validated['date'] ?? now($service->location?->timezone ?: config('app.timezone', 'UTC'))->toDateString());
        $resourceId = isset($validated['resource_id']) ? (int) $validated['resource_id'] : null;
        $payload = $this->reservations->availableSlots($service, $date, $resourceId);

        return response()->json([
            'data' => $payload['slots'],
            'meta' => [
                'resource_selection_mode' => $payload['mode'],
                'requires_resource' => $payload['requires_resource'],
                'resources' => $payload['resources'],
            ],
        ]);
    }

    public function book(Request $request, string $locale, string $slug): JsonResponse
    {
        $service = $this->catalog->findActiveServiceBySlug($locale, $slug);
        abort_unless($service, 404);

        $validated = $request->validate([
            'slot_id' => ['required', 'integer'],
            'resource_id' => ['nullable', 'integer', 'exists:booking_resources,id'],
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'customer_comment' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $booking = $this->reservations->reserveForPublicSelection(
                $service,
                (int) $validated['slot_id'],
                isset($validated['resource_id']) ? (int) $validated['resource_id'] : null,
                $validated,
                'public_page'
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'resource_id' => $booking->resource_id,
                'message' => $booking->status === 'confirmed'
                    ? ($locale === 'ru' ? 'Бронирование подтверждено.' : 'Booking confirmed.')
                    : ($locale === 'ru' ? 'Заявка принята и ожидает подтверждения.' : 'Booking request received and awaits confirmation.'),
            ],
        ], 201);
    }
}
