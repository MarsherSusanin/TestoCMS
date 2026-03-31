<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingWebhookEndpoint extends Model
{
    protected $table = 'booking_webhook_endpoints';

    protected $fillable = [
        'url',
        'secret',
        'subscribed_events',
        'is_active',
    ];

    protected $casts = [
        'subscribed_events' => 'array',
        'is_active' => 'boolean',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(BookingWebhookDelivery::class, 'webhook_endpoint_id');
    }
}
