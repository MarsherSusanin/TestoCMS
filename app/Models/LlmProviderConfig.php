<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmProviderConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'is_enabled',
        'model',
        'api_base',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }
}
