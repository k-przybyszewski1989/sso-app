<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

interface TokenGeneratorServiceInterface
{
    /**
     * Generates a cryptographically secure access token.
     */
    public function generateAccessToken(): string;

    /**
     * Generates a cryptographically secure refresh token.
     */
    public function generateRefreshToken(): string;

    /**
     * Generates a cryptographically secure authorization code.
     */
    public function generateAuthorizationCode(): string;

    /**
     * Generates a unique OAuth2 client identifier.
     */
    public function generateClientId(): string;

    /**
     * Generates a cryptographically secure client secret.
     */
    public function generateClientSecret(): string;
}
