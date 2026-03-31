<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlugHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'locale',
        'old_slug',
        'new_slug',
    ];
}
