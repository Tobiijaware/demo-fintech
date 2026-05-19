<?php

namespace App\Exceptions;

use Exception;

class DojahException extends Exception
{
    public function __construct(
        string $message,
        public int $statusCode = 422,
        public mixed $dojahResponse = null,
    ) {
        parent::__construct($message);
    }
}
