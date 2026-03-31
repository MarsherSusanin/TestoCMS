<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'disk',
        'storage_path',
        'public_url',
        'mime_type',
        'size',
        'width',
        'height',
        'checksum',
        'alt',
        'title',
        'caption',
        'credits',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
