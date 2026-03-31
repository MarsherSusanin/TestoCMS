<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingServiceTranslation extends Model
{
    protected $table = 'booking_service_translations';

    protected $fillable = [
        'service_id',
        'locale',
        'title',
        'slug',
        'short_description',
        'full_description',
        'meta_title',
        'meta_description',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(BookingService::class, 'service_id');
    }
}

