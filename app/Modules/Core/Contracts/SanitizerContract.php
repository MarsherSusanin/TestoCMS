<?php

namespace App\Modules\Core\Contracts;

interface SanitizerContract
{
    public function sanitizeHtml(string $html, string $profile = 'default'): string;
}
