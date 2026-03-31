<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingBooking extends Model
{
    protected $table = 'booking_bookings';

    protected $fillable = [
        'service_id',
        'location_id',
        'resource_id',
        'slot_occurrence_id',
        'starts_at',
        'ends_at',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_comment',
        'status',
        'invoice_status',
        'payment_status',
        'source',
        'internal_notes',
        'hold_expires_at',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'hold_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'meta' => 'array',
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

    public function slotOccurrence(): BelongsTo
    {
        return $this->belongsTo(BookingSlotOccurrence::class, 'slot_occurrence_id');
    }
}
