<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation',
        'provider',
        'model',
        'status',
        'entity_type',
        'entity_id',
        'created_by',
        'input_payload',
        'output_payload',
        'error_text',
    ];

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'output_payload' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
