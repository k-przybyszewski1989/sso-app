<?php

declare(strict_types=1);

namespace App\Service\OAuth2\Grant;

use App\Exception\OAuth2\InvalidRequestException;
use App\Exception\OAuth2\InvalidScopeException;
use App\Exception\OAuth2\UnauthorizedClientException;
use App\Request\OAuth2\TokenRequest;
use App\Response\OAuth2\TokenResponse;
use App\Service\OAuth2\AccessTokenServiceInterface;
use App\Service\OAuth2\ClientAuthenticationServiceInterface;
use App\Service\OAuth2\RefreshTokenServiceInterface;

final readonly class RefreshTokenGrantHandler implements GrantHandlerInterface
{
    private const string GRANT_TYPE = 'refresh_token';

    public function __construct(
        private ClientAuthenticationServiceInterface $clientAuthenticationService,
        private RefreshTokenServiceInterface $refreshTokenService,
        private AccessTokenServiceInterface $accessTokenService,
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
                'Client is not authorized to use refresh_token grant type'
            );
        }

        // Validate required parameters
        if (null === $request->refreshToken) {
            throw new InvalidRequestException('Refresh token is required');
        }

        // Validate and consume the refresh token (rotation - old token will be revoked)
        $oldRefreshToken = $this->refreshTokenService->validateAndConsumeToken(
            $request->refreshToken,
            $client
        );

        // Determine scopes for new tokens
        $scopes = $oldRefreshToken->getScopes();

        // If scope parameter is provided, validate it can only narrow, not expand
        if (null !== $request->scope) {
            $requestedScopes = explode(' ', $request->scope);

            // Check that all requested scopes were in the original token
            $invalidScopes = array_diff($requestedScopes, $scopes);
            if ([] !== $invalidScopes) {
                throw new InvalidScopeException(
                    'Requested scopes cannot exceed original grant: ' . implode(', ', $invalidScopes)
                );
            }

            $scopes = $requestedScopes;
        }

        // Create new access token
        $accessToken = $this->accessTokenService->createAccessToken(
            $client,
            $scopes,
            $oldRefreshToken->getUser()
        );

        // Create new refresh token (rotation)
        $newRefreshToken = $this->refreshTokenService->createRefreshToken(
            $client,
            $oldRefreshToken->getUser(),
            $scopes
        );

        return new TokenResponse(
            accessToken: $accessToken->getToken(),
            tokenType: 'Bearer',
            expiresIn: $accessToken->getExpiresAt()->getTimestamp() - time(),
            refreshToken: $newRefreshToken->getToken(),
            scope: implode(' ', $scopes),
        );
    }
}
