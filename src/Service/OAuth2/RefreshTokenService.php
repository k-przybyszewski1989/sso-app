<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\OAuth2Client;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\OAuth2\InvalidGrantException;
use App\Repository\RefreshTokenRepositoryInterface;
use DateMalformedStringException;
use DateTimeImmutable;

final readonly class RefreshTokenService implements RefreshTokenServiceInterface
{
    private const int TOKEN_LIFETIME_SECONDS = 2592000; // 30 days

    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private TokenGeneratorServiceInterface $tokenGenerator,
    ) {
    }

    /** {@inheritDoc}
     * @throws DateMalformedStringException
     */
    public function createRefreshToken(OAuth2Client $client, User $user, array $scopes): RefreshToken
    {
        $expiresAt = new DateTimeImmutable(sprintf('+%d seconds', self::TOKEN_LIFETIME_SECONDS));
        $tokenString = $this->tokenGenerator->generateRefreshToken();

        $token = new RefreshToken($tokenString, $client, $user, $expiresAt);
        $token->setScopes($scopes);

        $this->refreshTokenRepository->save($token);

        return $token;
    }

    /** {@inheritDoc} */
    public function validateAndConsumeToken(string $token, OAuth2Client $client): RefreshToken
    {
        $refreshToken = $this->refreshTokenRepository->findByToken($token);

        if (null === $refreshToken) {
            throw new InvalidGrantException('Invalid refresh token');
        }

        if (!$refreshToken->isValid()) {
            throw new InvalidGrantException('Refresh token is expired or revoked');
        }

        // Verify the token belongs to the authenticated client
        if ($refreshToken->getClient()->getId() !== $client->getId()) {
            throw new InvalidGrantException('Refresh token does not belong to this client');
        }

        // Revoke the old refresh token (token rotation)
        $refreshToken->setRevoked(true);
        $refreshToken->setRevokedAt(new DateTimeImmutable());
        $this->refreshTokenRepository->save($refreshToken);

        return $refreshToken;
    }

    /** {@inheritDoc} */
    public function revokeToken(string $token): void
    {
        $refreshToken = $this->refreshTokenRepository->findByToken($token);

        if (null === $refreshToken) {
            // Token doesn't exist - silently succeed per RFC 7009
            return;
        }

        if ($refreshToken->isRevoked()) {
            // Already revoked - silently succeed
            return;
        }

        $refreshToken->setRevoked(true);
        $refreshToken->setRevokedAt(new DateTimeImmutable());

        $this->refreshTokenRepository->save($refreshToken);
    }
}
