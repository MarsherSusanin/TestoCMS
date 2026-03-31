<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmPrompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_key',
        'locale',
        'prompt_text',
    ];
}
