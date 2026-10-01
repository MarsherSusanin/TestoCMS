<?php

namespace App\Modules\LLM\Exceptions;

use RuntimeException;

class LlmOutputException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
