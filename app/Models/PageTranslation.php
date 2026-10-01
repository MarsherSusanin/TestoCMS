<?php

namespace App\Models;

use App\Modules\Content\Services\SearchTextProjectionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'locale',
        'title',
        'search_text',
        'slug',
        'content_blocks',
        'rendered_html',
        'meta_title',
        'meta_description',
        'canonical_url',
        'custom_head_html',
        'robots_directives',
        'structured_data',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $translation): void {
            $translation->setAttribute('search_text', app(SearchTextProjectionService::class)->forPage($translation->attributesToArray()));
        });
    }

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'robots_directives' => 'array',
            'structured_data' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
