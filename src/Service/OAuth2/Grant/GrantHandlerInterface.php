<?php

declare(strict_types=1);

namespace App\Service\OAuth2\Grant;

use App\Request\OAuth2\TokenRequest;
use App\Response\OAuth2\TokenResponse;

interface GrantHandlerInterface
{
    /**
     * Checks if this handler supports the given grant type.
     */
    public function supports(string $grantType): bool;

    /**
     * Handles the token request and returns a token response.
     */
    public function handle(TokenRequest $request): TokenResponse;
}
