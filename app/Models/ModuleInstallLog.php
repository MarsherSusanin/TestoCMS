<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleInstallLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'cms_module_install_logs';

    protected $fillable = [
        'module_key',
        'action',
        'status',
        'context',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];
}
