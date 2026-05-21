<?php

namespace App\Exceptions;

use Exception;

class SwwipeException extends Exception
{
    public function __construct(
        string $message,
        public int $statusCode = 422,
        public mixed $response = null,
    ) {
        parent::__construct($message);
    }
}
