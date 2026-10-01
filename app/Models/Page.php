<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * @property Carbon|null $published_at
 * @property Carbon|null $archived_at
 */
class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'status',
        'page_type',
        'custom_code',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_code' => 'array',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    protected $appends = ['scheduled_actions'];

    /** @return HasMany<PublishSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(PublishSchedule::class, 'entity_id')->where('entity_type', 'page');
    }

    public function getScheduledActionsAttribute(): array
    {
        if (! Schema::hasColumn('publish_schedules', 'cancelled_at')) {
            return [];
        }

        return $this->schedules()->whereNull('executed_at')->whereNull('cancelled_at')->orderBy('due_at')->orderBy('id')->get(['id', 'action', 'due_at'])->map(static fn (PublishSchedule $schedule): array => [
            'id' => $schedule->id,
            'action' => $schedule->action,
            'due_at' => $schedule->due_at->toIso8601String(),
        ])->all();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }
}
