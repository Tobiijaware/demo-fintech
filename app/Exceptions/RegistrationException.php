<?php

namespace App\Exceptions;

use Exception;

class RegistrationException extends Exception
{
    public function __construct(string $message, public int $statusCode = 422)
    {
        parent::__construct($message);
    }
}
