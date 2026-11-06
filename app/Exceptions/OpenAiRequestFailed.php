<?php

namespace App\Exceptions;

use RuntimeException;

class OpenAiRequestFailed extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }
}
