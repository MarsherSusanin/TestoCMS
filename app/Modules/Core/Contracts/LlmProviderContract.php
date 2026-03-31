<?php

namespace App\Modules\Core\Contracts;

interface LlmProviderContract
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generate(array $payload): array;
}
