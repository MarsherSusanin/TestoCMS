<?php

namespace App\Modules\Content\Exceptions;

use RuntimeException;

class AssetInUseException extends RuntimeException
{
    /** @param list<array<string, mixed>> $usages */
    public function __construct(public readonly array $usages)
    {
        parent::__construct('Asset is in use. Remove its references before deleting it.');
    }
}
