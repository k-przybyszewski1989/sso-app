<?php

declare(strict_types=1);

namespace App\Exception\OAuth2;

final class InvalidGrantException extends OAuth2Exception
{
    public function __construct(string $errorDescription = 'The provided authorization grant is invalid')
    {
        parent::__construct('invalid_grant', $errorDescription, 400);
    }
}
