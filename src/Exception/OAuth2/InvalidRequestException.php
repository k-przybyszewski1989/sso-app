<?php

declare(strict_types=1);

namespace App\Exception\OAuth2;

final class InvalidRequestException extends OAuth2Exception
{
    public function __construct(string $errorDescription = 'The request is missing a required parameter')
    {
        parent::__construct('invalid_request', $errorDescription, 400);
    }
}
