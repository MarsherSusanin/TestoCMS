<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SitemapState extends Model
{
    use HasFactory;

    protected $table = 'sitemap_state';

    protected $fillable = [
        'type',
        'last_generated_at',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'last_generated_at' => 'datetime',
        ];
    }
}
