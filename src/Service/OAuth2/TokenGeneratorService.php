<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

final readonly class TokenGeneratorService implements TokenGeneratorServiceInterface
{
    /** {@inheritDoc} */
    public function generateAccessToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters
    }

    /** {@inheritDoc} */
    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters
    }

    /** {@inheritDoc} */
    public function generateAuthorizationCode(): string
    {
        return bin2hex(random_bytes(16)); // 32 characters
    }

    /** {@inheritDoc} */
    public function generateClientId(): string
    {
        return bin2hex(random_bytes(16)); // 32 characters
    }

    /** {@inheritDoc} */
    public function generateClientSecret(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters
    }
}
