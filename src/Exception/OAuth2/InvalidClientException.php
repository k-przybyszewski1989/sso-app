<?php

declare(strict_types=1);

namespace App\Exception\OAuth2;

final class InvalidClientException extends OAuth2Exception
{
    public function __construct(string $errorDescription = 'Client authentication failed')
    {
        parent::__construct('invalid_client', $errorDescription, 401);
    }
}
