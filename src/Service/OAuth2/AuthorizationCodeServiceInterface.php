<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\AuthorizationCode;
use App\Entity\OAuth2Client;
use App\Entity\User;

interface AuthorizationCodeServiceInterface
{
    /**
     * @param array<string> $scopes
     */
    public function createAuthorizationCode(
        OAuth2Client $client,
        User $user,
        string $redirectUri,
        array $scopes,
        ?string $codeChallenge = null,
        ?string $codeChallengeMethod = null,
    ): AuthorizationCode;

    /**
     * Validates, consumes, and returns the authorization code.
     *
     * @throws \App\Exception\OAuth2\OAuth2Exception If the code is invalid, expired, already used, or PKCE validation fails
     */
    public function validateAndConsumeCode(
        string $code,
        OAuth2Client $client,
        string $redirectUri,
        ?string $codeVerifier = null,
    ): AuthorizationCode;
}
