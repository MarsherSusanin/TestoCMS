<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'locale',
        'meta_title',
        'meta_description',
        'canonical_url',
        'robots_directives',
        'structured_data',
    ];

    protected function casts(): array
    {
        return [
            'robots_directives' => 'array',
            'structured_data' => 'array',
        ];
    }
}
