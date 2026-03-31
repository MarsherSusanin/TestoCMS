<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAvailabilityException extends Model
{
    protected $table = 'booking_availability_exceptions';

    protected $fillable = [
        'service_id',
        'location_id',
        'resource_id',
        'date',
        'start_time',
        'end_time',
        'is_closed',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'is_closed' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(BookingService::class, 'service_id');
    }
}
