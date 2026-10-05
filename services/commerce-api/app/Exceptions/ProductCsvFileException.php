<?php

namespace App\Exceptions;

use Exception;

class ProductCsvFileException extends Exception
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public readonly int $status,
        public readonly string $title,
        public readonly string $detail,
        public readonly string $problemCode,
        public readonly array $errors = [],
    ) {
        parent::__construct($detail);
    }
}
