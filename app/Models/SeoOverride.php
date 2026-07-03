<?php

namespace App\Models;

use App\Modules\SEO\Services\SeoCacheKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SeoOverride extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        $forget = function (self $override): void {
            Cache::forget(SeoCacheKeys::override(
                (string) $override->entity_type,
                (int) $override->entity_id,
                (string) $override->locale,
            ));
        };

        static::saved($forget);
        static::deleted($forget);
    }

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
