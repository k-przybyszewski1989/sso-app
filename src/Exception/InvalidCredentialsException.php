<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'Invalid credentials provided')
    {
        parent::__construct($message);
    }
}
