<?php

declare(strict_types=1);

namespace App\Exception\OAuth2;

final class InvalidScopeException extends OAuth2Exception
{
    public function __construct(string $errorDescription = 'The requested scope is invalid or unknown')
    {
        parent::__construct('invalid_scope', $errorDescription, 400);
    }
}
