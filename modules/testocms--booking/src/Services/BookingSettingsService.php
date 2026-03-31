<?php

namespace TestoCms\Booking\Services;

use TestoCms\Booking\Models\BookingSetting;

class BookingSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'hold_ttl_minutes' => 120,
            'projection_horizon_days' => 90,
            'public_prefix' => trim((string) config('cms.booking_url_prefix', 'book'), '/'),
            'webhooks_enabled' => true,
            'email_notifications' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolved(): array
    {
        $settings = BookingSetting::query()->first();
        $payload = is_array($settings?->settings) ? $settings->settings : [];

        return $this->normalize(array_merge($this->defaults(), $payload));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $payload = $this->normalize($input);
        $settings = BookingSetting::query()->firstOrNew(['id' => 1]);
        $settings->settings = $payload;
        $settings->save();

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $defaults = $this->defaults();

        return [
            'hold_ttl_minutes' => max(15, min(1440, (int) ($input['hold_ttl_minutes'] ?? $defaults['hold_ttl_minutes']))),
            'projection_horizon_days' => max(7, min(365, (int) ($input['projection_horizon_days'] ?? $defaults['projection_horizon_days']))),
            'public_prefix' => trim((string) ($input['public_prefix'] ?? $defaults['public_prefix']), '/'),
            'webhooks_enabled' => filter_var($input['webhooks_enabled'] ?? $defaults['webhooks_enabled'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'email_notifications' => filter_var($input['email_notifications'] ?? $defaults['email_notifications'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        ];
    }
}

