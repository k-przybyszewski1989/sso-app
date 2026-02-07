<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\OAuth2Client;
use App\Entity\RefreshToken;
use App\Entity\User;

interface RefreshTokenServiceInterface
{
    /**
     * @param array<string> $scopes
     */
    public function createRefreshToken(OAuth2Client $client, User $user, array $scopes): RefreshToken;

    /**
     * Validates, consumes, and returns the refresh token.
     *
     * @throws \App\Exception\OAuth2\OAuth2Exception If the token is invalid, expired, revoked, or already used
     */
    public function validateAndConsumeToken(string $token, OAuth2Client $client): RefreshToken;

    /**
     * Revokes the given refresh token.
     *
     * @throws \App\Exception\OAuth2\OAuth2Exception If the token is not found
     */
    public function revokeToken(string $token): void;
}
