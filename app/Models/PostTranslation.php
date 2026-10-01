<?php

namespace App\Models;

use App\Modules\Content\Services\SearchTextProjectionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'locale',
        'title',
        'search_text',
        'slug',
        'content_format',
        'content_html',
        'content_markdown',
        'content_plain',
        'excerpt',
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
            $translation->setAttribute('search_text', app(SearchTextProjectionService::class)->forPost($translation->attributesToArray()));
        });
    }

    protected function casts(): array
    {
        return [
            'robots_directives' => 'array',
            'structured_data' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
