<?php

namespace App\Exceptions;

use RuntimeException;

class GuestOrderClaimConflictException extends RuntimeException
{
    public function __construct(public readonly string $problemCode, string $message)
    {
        parent::__construct($message);
    }
}
