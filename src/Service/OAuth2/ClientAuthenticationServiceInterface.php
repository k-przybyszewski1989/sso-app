<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\OAuth2Client;

interface ClientAuthenticationServiceInterface
{
    /**
     * Authenticates a client using either HTTP Basic auth header or client credentials.
     *
     * @throws \App\Exception\OAuth2\OAuth2Exception If authentication fails
     */
    public function authenticate(?string $authHeader, ?string $clientId, ?string $clientSecret): OAuth2Client;
}
