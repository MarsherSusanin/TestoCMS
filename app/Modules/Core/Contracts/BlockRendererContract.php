<?php

namespace App\Modules\Core\Contracts;

interface BlockRendererContract
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $context
     */
    public function render(array $blocks, array $context = []): string;
}
