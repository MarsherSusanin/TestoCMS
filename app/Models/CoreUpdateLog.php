<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreUpdateLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'cms_core_update_logs';

    protected $fillable = [
        'action',
        'status',
        'from_version',
        'to_version',
        'message',
        'context',
        'actor_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
