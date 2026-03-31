<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreBackup extends Model
{
    use HasFactory;

    protected $table = 'cms_core_backups';

    protected $fillable = [
        'backup_key',
        'from_version',
        'to_version',
        'status',
        'backup_path',
        'db_dump_path',
        'manifest_path',
        'restore_status',
        'last_error',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
