<?php

declare(strict_types=1);

namespace App\Service\OAuth2\Grant;

use App\Exception\OAuth2\InvalidRequestException;
use App\Exception\OAuth2\UnauthorizedClientException;
use App\Request\OAuth2\TokenRequest;
use App\Response\OAuth2\TokenResponse;
use App\Service\OAuth2\AccessTokenServiceInterface;
use App\Service\OAuth2\AuthorizationCodeServiceInterface;
use App\Service\OAuth2\ClientAuthenticationServiceInterface;
use App\Service\OAuth2\RefreshTokenServiceInterface;

final readonly class AuthorizationCodeGrantHandler implements GrantHandlerInterface
{
    private const string GRANT_TYPE = 'authorization_code';

    public function __construct(
        private ClientAuthenticationServiceInterface $clientAuthenticationService,
        private AuthorizationCodeServiceInterface $authorizationCodeService,
        private AccessTokenServiceInterface $accessTokenService,
        private RefreshTokenServiceInterface $refreshTokenService,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $grantType): bool
    {
        return self::GRANT_TYPE === $grantType;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(TokenRequest $request): TokenResponse
    {
        // Authenticate the client
        $client = $this->clientAuthenticationService->authenticate(
            $request->authorizationHeader,
            $request->clientId,
            $request->clientSecret
        );

        // Validate that client is allowed to use this grant type
        if (!in_array(self::GRANT_TYPE, $client->getGrantTypes(), true)) {
            throw new UnauthorizedClientException(
                'Client is not authorized to use authorization_code grant type'
            );
        }

        // Validate required parameters
        if (null === $request->code) {
            throw new InvalidRequestException('Authorization code is required');
        }

        if (null === $request->redirectUri) {
            throw new InvalidRequestException('Redirect URI is required');
        }

        // Validate and consume the authorization code (includes PKCE validation)
        $authorizationCode = $this->authorizationCodeService->validateAndConsumeCode(
            $request->code,
            $client,
            $request->redirectUri,
            $request->codeVerifier
        );

        // Create access token
        $accessToken = $this->accessTokenService->createAccessToken(
            $client,
            $authorizationCode->getScopes(),
            $authorizationCode->getUser()
        );

        // Create refresh token if offline_access scope is present
        $refreshToken = null;
        if (in_array('offline_access', $authorizationCode->getScopes(), true)) {
            $refreshTokenEntity = $this->refreshTokenService->createRefreshToken(
                $client,
                $authorizationCode->getUser(),
                $authorizationCode->getScopes()
            );
            $refreshToken = $refreshTokenEntity->getToken();
        }

        return new TokenResponse(
            accessToken: $accessToken->getToken(),
            tokenType: 'Bearer',
            expiresIn: $accessToken->getExpiresAt()->getTimestamp() - time(),
            refreshToken: $refreshToken,
            scope: implode(' ', $authorizationCode->getScopes()),
        );
    }
}
