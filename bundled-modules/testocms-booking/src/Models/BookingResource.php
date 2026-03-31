<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookingResource extends Model
{
    protected $table = 'booking_resources';

    protected $fillable = [
        'location_id',
        'name',
        'resource_type',
        'description',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(BookingLocation::class, 'location_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(BookingService::class, 'booking_service_resource', 'resource_id', 'service_id')
            ->withTimestamps();
    }
}

