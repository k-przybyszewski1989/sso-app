<?php

declare(strict_types=1);

namespace App\Exception\OAuth2;

final class InvalidTokenException extends OAuth2Exception
{
    public function __construct(string $errorDescription = 'The access token is invalid or expired')
    {
        parent::__construct('invalid_token', $errorDescription, 401);
    }
}
