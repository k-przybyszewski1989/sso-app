<?php

declare(strict_types=1);

namespace App\Exception\OAuth2;

final class UnsupportedGrantTypeException extends OAuth2Exception
{
    public function __construct(string $errorDescription = 'The authorization grant type is not supported')
    {
        parent::__construct('unsupported_grant_type', $errorDescription, 400);
    }
}
