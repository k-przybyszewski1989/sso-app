<?php

declare(strict_types=1);

namespace App\Service\OAuth2\Grant;

use App\Enum\GrantType;
use App\Exception\OAuth2\InvalidRequestException;
use App\Exception\OAuth2\InvalidScopeException;
use App\Exception\OAuth2\UnauthorizedClientException;
use App\Request\OAuth2\TokenRequest;
use App\Response\OAuth2\TokenResponse;
use App\Service\OAuth2\AccessTokenServiceInterface;
use App\Service\OAuth2\ClientAuthenticationServiceInterface;
use App\Service\OAuth2\ScopeValidationServiceInterface;

final readonly class ClientCredentialsGrantHandler implements GrantHandlerInterface
{
    private const GrantType GRANT_TYPE = GrantType::CLIENT_CREDENTIALS;

    public function __construct(
        private ClientAuthenticationServiceInterface $clientAuthenticationService,
        private ScopeValidationServiceInterface $scopeValidationService,
        private AccessTokenServiceInterface $accessTokenService,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $grantType): bool
    {
        return self::GRANT_TYPE->value === $grantType;
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
                'Client is not authorized to use client_credentials grant type'
            );
        }

        // Parse and validate scopes
        $requestedScopes = null !== $request->scope
            ? explode(' ', $request->scope)
            : [];

        if ([] === $requestedScopes) {
            throw new InvalidRequestException('Scope parameter is required for client_credentials grant');
        }

        try {
            $validScopes = $this->scopeValidationService->validate($requestedScopes, $client->getAllowedScopes());
        } catch (InvalidScopeException $e) {
            throw $e;
        }

        // Create access token (no user for client credentials)
        $accessToken = $this->accessTokenService->createAccessToken(
            $client,
            $validScopes,
            null
        );

        // Return token response (no refresh token for client credentials)
        return new TokenResponse(
            accessToken: $accessToken->getToken(),
            tokenType: 'Bearer',
            expiresIn: $accessToken->getExpiresAt()->getTimestamp() - time(),
            scope: implode(' ', $validScopes),
        );
    }
}
