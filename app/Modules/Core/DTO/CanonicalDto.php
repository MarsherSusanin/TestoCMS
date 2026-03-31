<?php

namespace App\Modules\Core\DTO;

class CanonicalDto
{
    public function __construct(public string $url) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['canonical_url' => $this->url];
    }
}
