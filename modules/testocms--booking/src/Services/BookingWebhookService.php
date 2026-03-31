<?php

namespace TestoCms\Booking\Services;

use Illuminate\Support\Facades\Http;
use TestoCms\Booking\Models\BookingWebhookDelivery;
use TestoCms\Booking\Models\BookingWebhookEndpoint;

class BookingWebhookService
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_EVENTS = [
        'booking.created',
        'booking.confirmed',
        'booking.cancelled',
        'booking.completed',
        'booking.no_show',
        'booking.rescheduled',
    ];

    public function __construct(private readonly BookingSettingsService $settings)
    {
    }

    /**
     * @return array<int, string>
     */
    public function supportedEvents(): array
    {
        return self::SUPPORTED_EVENTS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $eventName, array $payload): void
    {
        if (! ($this->settings->resolved()['webhooks_enabled'] ?? true)) {
            return;
        }

        BookingWebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (BookingWebhookEndpoint $endpoint) use ($eventName): bool {
                $events = is_array($endpoint->subscribed_events) ? $endpoint->subscribed_events : [];

                return $events === [] || in_array($eventName, $events, true);
            })
            ->each(function (BookingWebhookEndpoint $endpoint) use ($eventName, $payload): void {
                $this->deliverToEndpoint($endpoint, $eventName, $payload);
            });
    }

    public function retry(BookingWebhookDelivery $delivery): BookingWebhookDelivery
    {
        $delivery->loadMissing('endpoint');

        $endpoint = $delivery->endpoint;
        if (! $endpoint instanceof BookingWebhookEndpoint) {
            throw new \RuntimeException('Endpoint для повторной отправки не найден.');
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($delivery->payload) ? $delivery->payload : [];

        return $this->deliverToEndpoint($endpoint, (string) $delivery->event_name, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliverToEndpoint(BookingWebhookEndpoint $endpoint, string $eventName, array $payload): BookingWebhookDelivery
    {
        $delivery = BookingWebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders(array_filter([
                    'X-Booking-Event' => $eventName,
                    'X-Booking-Signature' => $endpoint->secret ? hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) $endpoint->secret) : null,
                ]))
                ->post($endpoint->url, $payload);

            $delivery->forceFill([
                'status' => $response->successful() ? 'delivered' : 'failed',
                'http_status' => $response->status(),
                'response_body' => $response->body(),
                'attempted_at' => now(),
                'delivered_at' => $response->successful() ? now() : null,
            ])->save();
        } catch (\Throwable $e) {
            $delivery->forceFill([
                'status' => 'failed',
                'response_body' => $e->getMessage(),
                'attempted_at' => now(),
            ])->save();
        }

        return $delivery;
    }
}
