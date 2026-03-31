<?php

namespace TestoCms\Booking\Services;

use TestoCms\Booking\Models\BookingAvailabilityException;
use TestoCms\Booking\Models\BookingAvailabilityRule;
use TestoCms\Booking\Models\BookingService;

class BookingAvailabilityService
{
    public function __construct(private readonly BookingSlotProjectionService $projection)
    {
    }

    public function storeRule(array $payload): BookingAvailabilityRule
    {
        $rule = BookingAvailabilityRule::query()->create($payload);
        $this->projection->rebuildService($rule->service()->firstOrFail());

        return $rule;
    }

    public function updateRule(BookingAvailabilityRule $rule, array $payload): BookingAvailabilityRule
    {
        $rule->fill($payload);
        $rule->save();
        $this->projection->rebuildService($rule->service()->firstOrFail());

        return $rule;
    }

    public function destroyRule(BookingAvailabilityRule $rule): void
    {
        $service = $rule->service()->first();
        $rule->delete();
        if ($service) {
            $this->projection->rebuildService($service);
        }
    }

    public function storeException(array $payload): BookingAvailabilityException
    {
        $exception = BookingAvailabilityException::query()->create($payload);
        $this->projection->rebuildService($exception->service()->firstOrFail());

        return $exception;
    }

    public function destroyException(BookingAvailabilityException $exception): void
    {
        $service = $exception->service()->first();
        $exception->delete();
        if ($service) {
            $this->projection->rebuildService($service);
        }
    }

    public function rebuildService(BookingService $service): int
    {
        return $this->projection->rebuildService($service);
    }
}

