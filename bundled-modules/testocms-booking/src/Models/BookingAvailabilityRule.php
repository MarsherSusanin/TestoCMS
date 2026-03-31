<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAvailabilityRule extends Model
{
    protected $table = 'booking_availability_rules';

    protected $fillable = [
        'service_id',
        'location_id',
        'resource_id',
        'weekday',
        'start_time',
        'end_time',
        'slot_step_minutes',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'slot_step_minutes' => 'integer',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(BookingService::class, 'service_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(BookingLocation::class, 'location_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookingResource::class, 'resource_id');
    }
}
