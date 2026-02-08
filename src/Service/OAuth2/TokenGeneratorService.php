<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use Random\RandomException;

final readonly class TokenGeneratorService implements TokenGeneratorServiceInterface
{
    /** {@inheritDoc}
     * @throws RandomException
     */
    public function generateAccessToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters
    }

    /** {@inheritDoc}
     * @throws RandomException
     */
    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters
    }

    /** {@inheritDoc}
     * @throws RandomException
     */
    public function generateAuthorizationCode(): string
    {
        return bin2hex(random_bytes(16)); // 32 characters
    }

    /** {@inheritDoc}
     * @throws RandomException
     */
    public function generateClientId(): string
    {
        return bin2hex(random_bytes(16)); // 32 characters
    }

    /** {@inheritDoc}
     * @throws RandomException
     */
    public function generateClientSecret(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters
    }
}
