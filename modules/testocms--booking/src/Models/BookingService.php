<?php

namespace TestoCms\Booking\Models;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingService extends Model
{
    protected $table = 'booking_services';

    protected $fillable = [
        'location_id',
        'featured_asset_id',
        'duration_minutes',
        'slot_step_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'booking_horizon_days',
        'lead_time_minutes',
        'confirmation_mode',
        'resource_selection_mode',
        'price_amount',
        'price_currency',
        'price_label',
        'cta_label',
        'is_active',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'slot_step_minutes' => 'integer',
        'buffer_before_minutes' => 'integer',
        'buffer_after_minutes' => 'integer',
        'booking_horizon_days' => 'integer',
        'lead_time_minutes' => 'integer',
        'price_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function usesResourceChoiceMode(): bool
    {
        return (string) $this->resource_selection_mode === 'choose_resource';
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(BookingLocation::class, 'location_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'featured_asset_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BookingServiceTranslation::class, 'service_id');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(BookingResource::class, 'booking_service_resource', 'service_id', 'resource_id')
            ->withTimestamps();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(BookingAvailabilityRule::class, 'service_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(BookingAvailabilityException::class, 'service_id');
    }
}
