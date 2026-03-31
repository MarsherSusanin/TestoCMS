<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsModule extends Model
{
    use HasFactory;

    protected $table = 'cms_modules';

    protected $fillable = [
        'module_key',
        'name',
        'version',
        'description',
        'author',
        'install_path',
        'provider',
        'checksum',
        'enabled',
        'status',
        'installed_at',
        'updated_at_module',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'installed_at' => 'datetime',
        'updated_at_module' => 'datetime',
        'metadata' => 'array',
    ];
}
