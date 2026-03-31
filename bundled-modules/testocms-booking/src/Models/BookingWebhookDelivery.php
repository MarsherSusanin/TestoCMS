<?php

namespace TestoCms\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingWebhookDelivery extends Model
{
    protected $table = 'booking_webhook_deliveries';

    protected $fillable = [
        'webhook_endpoint_id',
        'event_name',
        'payload',
        'status',
        'http_status',
        'response_body',
        'attempted_at',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempted_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(BookingWebhookEndpoint::class, 'webhook_endpoint_id');
    }
}

