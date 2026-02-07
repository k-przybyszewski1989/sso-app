<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\User;

interface AccessTokenServiceInterface
{
    /**
     * @param array<string> $scopes
     */
    public function createAccessToken(OAuth2Client $client, array $scopes, ?User $user = null): AccessToken;

    /**
     * Validates and returns the access token.
     *
     * @throws \App\Exception\OAuth2\OAuth2Exception If the token is invalid, expired, or revoked
     */
    public function validateToken(string $token): AccessToken;

    /**
     * Revokes the given access token.
     *
     * @throws \App\Exception\OAuth2\OAuth2Exception If the token is not found
     */
    public function revokeToken(string $token): void;
}
