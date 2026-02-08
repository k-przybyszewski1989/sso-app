<?php

declare(strict_types=1);

namespace App\Exception\OAuth2;

final class UnauthorizedClientException extends OAuth2Exception
{
    public function __construct(string $errorDescription = 'The client is not authorized to use this grant type')
    {
        parent::__construct('unauthorized_client', $errorDescription, 400);
    }
}
