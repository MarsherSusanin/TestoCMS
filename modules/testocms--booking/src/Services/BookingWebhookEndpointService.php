<?php

namespace TestoCms\Booking\Services;

use Illuminate\Support\Str;
use TestoCms\Booking\Models\BookingWebhookEndpoint;

class BookingWebhookEndpointService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): BookingWebhookEndpoint
    {
        $endpoint = new BookingWebhookEndpoint;
        $endpoint->fill($this->normalizePayload($payload));
        if (blank($endpoint->secret)) {
            $endpoint->secret = $this->generateSecret();
        }
        $endpoint->save();

        return $endpoint;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(BookingWebhookEndpoint $endpoint, array $payload): BookingWebhookEndpoint
    {
        $normalized = $this->normalizePayload($payload);
        if (blank($normalized['secret'] ?? null)) {
            unset($normalized['secret']);
        }

        $endpoint->fill($normalized);
        $endpoint->save();

        return $endpoint;
    }

    public function rotateSecret(BookingWebhookEndpoint $endpoint): BookingWebhookEndpoint
    {
        $endpoint->secret = $this->generateSecret();
        $endpoint->save();

        return $endpoint;
    }

    public function delete(BookingWebhookEndpoint $endpoint): void
    {
        $endpoint->delete();
    }

    public function generateSecret(): string
    {
        return Str::random(48);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $events = $payload['subscribed_events'] ?? [];
        if (is_string($events)) {
            $events = array_filter(array_map('trim', explode(',', $events)));
        }

        return [
            'url' => trim((string) ($payload['url'] ?? '')),
            'secret' => blank($payload['secret'] ?? null) ? null : trim((string) $payload['secret']),
            'subscribed_events' => array_values(array_unique(array_values(array_filter(array_map('strval', is_array($events) ? $events : []))))),
            'is_active' => (bool) ($payload['is_active'] ?? false),
        ];
    }
}
