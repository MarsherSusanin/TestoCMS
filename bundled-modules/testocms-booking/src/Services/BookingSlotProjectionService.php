<?php

namespace TestoCms\Booking\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use TestoCms\Booking\Models\BookingAvailabilityException;
use TestoCms\Booking\Models\BookingAvailabilityRule;
use TestoCms\Booking\Models\BookingService;
use TestoCms\Booking\Models\BookingSlotOccurrence;

class BookingSlotProjectionService
{
    public function __construct(private readonly BookingSettingsService $settings)
    {
    }

    public function rebuildService(BookingService $service): int
    {
        return $this->rebuildServiceReport($service)['projected'];
    }

    /**
     * @return array{created:int,updated:int,pruned:int,projected:int}
     */
    public function rebuildServiceReport(BookingService $service): array
    {
        $service->loadMissing(['rules', 'exceptions']);

        $timezone = $service->location?->timezone ?: config('app.timezone', 'UTC');
        $horizonDays = max(1, (int) ($service->booking_horizon_days ?: $this->settings->resolved()['projection_horizon_days']));
        $now = CarbonImmutable::now($timezone)->startOfDay();
        $until = $now->addDays($horizonDays);
        $generatedKeys = [];
        $report = [
            'created' => 0,
            'updated' => 0,
            'pruned' => 0,
            'projected' => 0,
        ];

        DB::transaction(function () use ($service, $timezone, $now, $until, &$generatedKeys, &$report): void {
            for ($cursor = $now; $cursor->lessThanOrEqualTo($until); $cursor = $cursor->addDay()) {
                $weekday = ((int) $cursor->dayOfWeekIso) % 7;
                $rules = $service->rules
                    ->where('is_active', true)
                    ->where('weekday', $weekday)
                    ->values();

                foreach ($rules as $rule) {
                    if (! $rule instanceof BookingAvailabilityRule) {
                        continue;
                    }

                    $step = max(5, (int) ($rule->slot_step_minutes ?: $service->slot_step_minutes));
                    $capacity = max(1, (int) $rule->capacity);
                    $slotStart = CarbonImmutable::parse($cursor->toDateString().' '.$rule->start_time, $timezone);
                    $windowEnd = CarbonImmutable::parse($cursor->toDateString().' '.$rule->end_time, $timezone);
                    $duration = max(5, (int) $service->duration_minutes) + max(0, (int) $service->buffer_after_minutes);
                    $leadMinutes = max(0, (int) $service->lead_time_minutes);

                    while ($slotStart->addMinutes($duration)->lessThanOrEqualTo($windowEnd)) {
                        if ($slotStart->lessThan(CarbonImmutable::now($timezone)->addMinutes($leadMinutes))) {
                            $slotStart = $slotStart->addMinutes($step);
                            continue;
                        }

                        if ($this->isBlockedByException($service, $rule, $slotStart, $slotStart->addMinutes($duration))) {
                            $slotStart = $slotStart->addMinutes($step);
                            continue;
                        }

                        $attributes = [
                            'service_id' => $service->id,
                            'location_id' => $rule->location_id ?: $service->location_id,
                            'resource_id' => $rule->resource_id,
                            'starts_at' => $slotStart->setTimezone('UTC')->toDateTimeString(),
                        ];
                        $values = [
                            'source_rule_id' => $rule->id,
                            'ends_at' => $slotStart->addMinutes($duration)->setTimezone('UTC')->toDateTimeString(),
                            'capacity_total' => $capacity,
                            'status' => 'open',
                            'generated_at' => now(),
                        ];

                        $slot = BookingSlotOccurrence::query()->firstOrNew($attributes);
                        $wasExisting = $slot->exists;
                        $slot->fill($values);
                        $slot->save();

                        $generatedKeys[] = implode(':', [
                            $slot->service_id,
                            $slot->location_id ?: 0,
                            $slot->resource_id ?: 0,
                            $slot->starts_at?->format('Y-m-d H:i:s'),
                        ]);
                        if ($wasExisting) {
                            $report['updated']++;
                        } else {
                            $report['created']++;
                        }
                        $report['projected']++;
                        $slotStart = $slotStart->addMinutes($step);
                    }
                }
            }

            $prunable = BookingSlotOccurrence::query()
                ->where('service_id', $service->id)
                ->whereBetween('starts_at', [$now->setTimezone('UTC')->toDateTimeString(), $until->endOfDay()->setTimezone('UTC')->toDateTimeString()])
                ->where('reserved_count', 0)
                ->where('confirmed_count', 0)
                ->get();

            foreach ($prunable as $slot) {
                $key = implode(':', [
                    $slot->service_id,
                    $slot->location_id ?: 0,
                    $slot->resource_id ?: 0,
                    $slot->starts_at?->format('Y-m-d H:i:s'),
                ]);
                if (! in_array($key, $generatedKeys, true)) {
                    $slot->delete();
                    $report['pruned']++;
                }
            }
        });

        return $report;
    }

    public function rebuildAll(): int
    {
        return $this->rebuildAllReport()['projected'];
    }

    /**
     * @return array{services:int,created:int,updated:int,pruned:int,projected:int}
     */
    public function rebuildAllReport(): array
    {
        $report = [
            'services' => 0,
            'created' => 0,
            'updated' => 0,
            'pruned' => 0,
            'projected' => 0,
        ];

        BookingService::query()->where('is_active', true)->chunkById(50, function ($services) use (&$report): void {
            foreach ($services as $service) {
                $serviceReport = $this->rebuildServiceReport($service);
                $report['services']++;
                $report['created'] += $serviceReport['created'];
                $report['updated'] += $serviceReport['updated'];
                $report['pruned'] += $serviceReport['pruned'];
                $report['projected'] += $serviceReport['projected'];
            }
        });

        return $report;
    }

    private function isBlockedByException(BookingService $service, BookingAvailabilityRule $rule, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $date = $startsAt->toDateString();
        $exceptions = $service->exceptions
            ->where('date', Carbon::parse($date)->toDateString())
            ->filter(function (mixed $exception) use ($rule): bool {
                return $exception instanceof BookingAvailabilityException
                    && ($exception->location_id === null || (int) $exception->location_id === (int) ($rule->location_id ?: $service->location_id))
                    && ($exception->resource_id === null || (int) $exception->resource_id === (int) $rule->resource_id);
            });

        foreach ($exceptions as $exception) {
            if (! $exception instanceof BookingAvailabilityException) {
                continue;
            }
            if ($exception->start_time === null || $exception->end_time === null) {
                return true;
            }
            $blockedStart = CarbonImmutable::parse($date.' '.$exception->start_time, $startsAt->timezone);
            $blockedEnd = CarbonImmutable::parse($date.' '.$exception->end_time, $startsAt->timezone);
            if ($startsAt->lt($blockedEnd) && $endsAt->gt($blockedStart)) {
                return true;
            }
        }

        return false;
    }
}
