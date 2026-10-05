<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutConflictException extends RuntimeException
{
    public function __construct(public readonly string $problemCode, string $message)
    {
        parent::__construct($message);
    }
}
