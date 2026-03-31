<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'locale',
        'title',
        'slug',
        'description',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
