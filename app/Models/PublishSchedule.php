<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $due_at
 * @property Carbon|null $executed_at
 * @property Carbon|null $cancelled_at
 */
class PublishSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'due_at',
        'executed_at',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'pending_slot',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'executed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $schedule): void {
            $schedule->pending_slot = $schedule->executed_at === null && $schedule->cancelled_at === null ? 1 : null;
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('executed_at')->whereNull('cancelled_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
