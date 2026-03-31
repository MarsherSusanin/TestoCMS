<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingLocation extends Model
{
    protected $table = 'booking_locations';

    protected $fillable = [
        'name',
        'timezone',
        'address',
        'contact_email',
        'contact_phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(BookingService::class, 'location_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(BookingResource::class, 'location_id');
    }
}
