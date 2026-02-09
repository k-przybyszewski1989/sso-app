<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2\Grant;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Enum\GrantType;
use App\Exception\OAuth2\InvalidRequestException;
use App\Exception\OAuth2\InvalidScopeException;
use App\Exception\OAuth2\UnauthorizedClientException;
use App\Request\OAuth2\TokenRequest;
use App\Service\OAuth2\AccessTokenServiceInterface;
use App\Service\OAuth2\ClientAuthenticationServiceInterface;
use App\Service\OAuth2\Grant\ClientCredentialsGrantHandler;
use App\Service\OAuth2\ScopeValidationServiceInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClientCredentialsGrantHandlerTest extends TestCase
{
    public function testSupportsClientCredentialsGrantType(): void
    {
        $clientAuthService = $this->createStub(ClientAuthenticationServiceInterface::class);
        $scopeValidationService = $this->createStub(ScopeValidationServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);

        $handler = new ClientCredentialsGrantHandler(
            $clientAuthService,
            $scopeValidationService,
            $accessTokenService
        );

        $this->assertTrue($handler->supports('client_credentials'));
        $this->assertFalse($handler->supports('authorization_code'));
        $this->assertFalse($handler->supports('refresh_token'));
    }

    public function testHandleSuccessfully(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::CLIENT_CREDENTIALS,
            clientId: 'test_client',
            clientSecret: 'test_secret',
            scope: 'read write'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['client_credentials']);
        $client->setAllowedScopes(['read', 'write', 'admin']);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $accessToken = new AccessToken('generated_token', $client, $expiresAt);
        $accessToken->setScopes(['read', 'write']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->with(null, 'test_client', 'test_secret')
            ->willReturn($client);

        $scopeValidationService = $this->createMock(ScopeValidationServiceInterface::class);
        $scopeValidationService->expects($this->once())
            ->method('validate')
            ->with(['read', 'write'], ['read', 'write', 'admin'])
            ->willReturn(['read', 'write']);

        $accessTokenService = $this->createMock(AccessTokenServiceInterface::class);
        $accessTokenService->expects($this->once())
            ->method('createAccessToken')
            ->with($client, ['read', 'write'], null)
            ->willReturn($accessToken);

        $handler = new ClientCredentialsGrantHandler(
            $clientAuthService,
            $scopeValidationService,
            $accessTokenService
        );

        $response = $handler->handle($request);

        $this->assertSame('generated_token', $response->accessToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertNull($response->refreshToken);
        $this->assertSame('read write', $response->scope);
    }

    public function testHandleThrowsExceptionWhenClientNotAuthorizedForGrantType(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::CLIENT_CREDENTIALS,
            clientId: 'test_client',
            clientSecret: 'test_secret',
            scope: 'read'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['authorization_code']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $scopeValidationService = $this->createStub(ScopeValidationServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);

        $handler = new ClientCredentialsGrantHandler(
            $clientAuthService,
            $scopeValidationService,
            $accessTokenService
        );

        $this->expectException(UnauthorizedClientException::class);
        $this->expectExceptionMessage('Client is not authorized to use client_credentials grant type');

        $handler->handle($request);
    }

    public function testHandleThrowsExceptionWhenScopeParameterMissing(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::CLIENT_CREDENTIALS,
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['client_credentials']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $scopeValidationService = $this->createStub(ScopeValidationServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);

        $handler = new ClientCredentialsGrantHandler(
            $clientAuthService,
            $scopeValidationService,
            $accessTokenService
        );

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Scope parameter is required for client_credentials grant');

        $handler->handle($request);
    }

    public function testHandleThrowsExceptionWhenInvalidScope(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::CLIENT_CREDENTIALS,
            clientId: 'test_client',
            clientSecret: 'test_secret',
            scope: 'read invalid_scope'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['client_credentials']);
        $client->setAllowedScopes(['read', 'write']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $scopeValidationService = $this->createMock(ScopeValidationServiceInterface::class);
        $scopeValidationService->expects($this->once())
            ->method('validate')
            ->willThrowException(new InvalidScopeException('Invalid scopes requested: invalid_scope'));

        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);

        $handler = new ClientCredentialsGrantHandler(
            $clientAuthService,
            $scopeValidationService,
            $accessTokenService
        );

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('Invalid scopes requested: invalid_scope');

        $handler->handle($request);
    }
}
