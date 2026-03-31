<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'robots_txt_custom',
        'llms_txt_intro',
    ];

    /**
     * Get the global singleton instance.
     */
    public static function global(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'robots_txt_custom' => null,
                'llms_txt_intro' => null,
            ]
        );
    }
}
