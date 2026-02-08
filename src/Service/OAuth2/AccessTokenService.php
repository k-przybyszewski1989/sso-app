<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\Exception\OAuth2\InvalidTokenException;
use App\Repository\AccessTokenRepositoryInterface;
use DateTimeImmutable;

final readonly class AccessTokenService implements AccessTokenServiceInterface
{
    private const int TOKEN_LIFETIME_SECONDS = 3600; // 1 hour

    public function __construct(
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private TokenGeneratorServiceInterface $tokenGenerator,
    ) {
    }

    /** {@inheritDoc} */
    public function createAccessToken(OAuth2Client $client, array $scopes, ?User $user = null): AccessToken
    {
        $expiresAt = new DateTimeImmutable(sprintf('+%d seconds', self::TOKEN_LIFETIME_SECONDS));
        $tokenString = $this->tokenGenerator->generateAccessToken();

        $token = new AccessToken($tokenString, $client, $expiresAt);
        $token->setUser($user);
        $token->setScopes($scopes);

        $this->accessTokenRepository->save($token);

        return $token;
    }

    /** {@inheritDoc} */
    public function validateToken(string $token): AccessToken
    {
        $accessToken = $this->accessTokenRepository->findByToken($token);

        if (null === $accessToken) {
            throw new InvalidTokenException('Invalid access token');
        }

        if (!$accessToken->isValid()) {
            throw new InvalidTokenException('Access token is expired or revoked');
        }

        return $accessToken;
    }

    /** {@inheritDoc} */
    public function revokeToken(string $token): void
    {
        $accessToken = $this->accessTokenRepository->findByToken($token);

        if (null === $accessToken) {
            // Token doesn't exist - silently succeed per RFC 7009
            return;
        }

        if ($accessToken->isRevoked()) {
            // Already revoked - silently succeed
            return;
        }

        $accessToken->setRevoked(true);
        $accessToken->setRevokedAt(new DateTimeImmutable());

        $this->accessTokenRepository->save($accessToken);
    }
}
