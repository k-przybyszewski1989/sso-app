<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\AuthorizationCode;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\Exception\OAuth2\InvalidGrantException;
use App\Exception\OAuth2\InvalidRequestException;
use App\Repository\AuthorizationCodeRepositoryInterface;
use DateTimeImmutable;

final readonly class AuthorizationCodeService implements AuthorizationCodeServiceInterface
{
    private const int CODE_LIFETIME_SECONDS = 600; // 10 minutes

    public function __construct(
        private AuthorizationCodeRepositoryInterface $authorizationCodeRepository,
        private TokenGeneratorServiceInterface $tokenGenerator,
        private PkceServiceInterface $pkceService,
    ) {
    }

    /** {@inheritDoc} */
    public function createAuthorizationCode(
        OAuth2Client $client,
        User $user,
        string $redirectUri,
        array $scopes,
        ?string $codeChallenge = null,
        ?string $codeChallengeMethod = null,
    ): AuthorizationCode {
        $expiresAt = new DateTimeImmutable(sprintf('+%d seconds', self::CODE_LIFETIME_SECONDS));
        $codeString = $this->tokenGenerator->generateAuthorizationCode();

        $code = new AuthorizationCode($codeString, $client, $user, $redirectUri, $expiresAt);
        $code->setScopes($scopes);

        if (null !== $codeChallenge) {
            $code->setCodeChallenge($codeChallenge);
            $code->setCodeChallengeMethod($codeChallengeMethod ?? 'plain');
        }

        $this->authorizationCodeRepository->save($code);

        return $code;
    }

    /** {@inheritDoc} */
    public function validateAndConsumeCode(
        string $code,
        OAuth2Client $client,
        string $redirectUri,
        ?string $codeVerifier = null,
    ): AuthorizationCode {
        $authorizationCode = $this->authorizationCodeRepository->findByCode($code);

        if (null === $authorizationCode) {
            throw new InvalidGrantException('Invalid authorization code');
        }

        if (!$authorizationCode->isValid()) {
            throw new InvalidGrantException('Authorization code is expired or has been used');
        }

        // Verify the code belongs to the authenticated client
        if ($authorizationCode->getClient()->getId() !== $client->getId()) {
            throw new InvalidGrantException('Authorization code does not belong to this client');
        }

        // Verify redirect URI matches
        if ($authorizationCode->getRedirectUri() !== $redirectUri) {
            throw new InvalidGrantException('Redirect URI mismatch');
        }

        // Validate PKCE if challenge was provided during authorization
        if (null !== $authorizationCode->getCodeChallenge()) {
            if (null === $codeVerifier) {
                throw new InvalidRequestException('Code verifier required for PKCE');
            }

            $method = $authorizationCode->getCodeChallengeMethod() ?? 'plain';
            $isValid = $this->pkceService->validate(
                $codeVerifier,
                $authorizationCode->getCodeChallenge(),
                $method
            );

            if (!$isValid) {
                throw new InvalidGrantException('PKCE validation failed');
            }
        }

        // Mark the code as used
        $authorizationCode->setUsed(true);
        $authorizationCode->setUsedAt(new DateTimeImmutable());
        $this->authorizationCodeRepository->save($authorizationCode);

        return $authorizationCode;
    }
}
