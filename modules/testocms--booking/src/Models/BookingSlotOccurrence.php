<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingSlotOccurrence extends Model
{
    protected $table = 'booking_slot_occurrences';

    protected $fillable = [
        'service_id',
        'location_id',
        'resource_id',
        'source_rule_id',
        'starts_at',
        'ends_at',
        'capacity_total',
        'reserved_count',
        'confirmed_count',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'generated_at' => 'datetime',
        'capacity_total' => 'integer',
        'reserved_count' => 'integer',
        'confirmed_count' => 'integer',
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

    public function bookings(): HasMany
    {
        return $this->hasMany(BookingBooking::class, 'slot_occurrence_id');
    }
}
