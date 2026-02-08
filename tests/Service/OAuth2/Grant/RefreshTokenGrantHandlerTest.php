<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2\Grant;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\OAuth2\InvalidRequestException;
use App\Exception\OAuth2\InvalidScopeException;
use App\Exception\OAuth2\UnauthorizedClientException;
use App\Request\OAuth2\TokenRequest;
use App\Service\OAuth2\AccessTokenServiceInterface;
use App\Service\OAuth2\ClientAuthenticationServiceInterface;
use App\Service\OAuth2\Grant\RefreshTokenGrantHandler;
use App\Service\OAuth2\RefreshTokenServiceInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RefreshTokenGrantHandlerTest extends TestCase
{
    public function testSupportsRefreshTokenGrantType(): void
    {
        $clientAuthService = $this->createStub(ClientAuthenticationServiceInterface::class);
        $refreshTokenService = $this->createStub(RefreshTokenServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);

        $handler = new RefreshTokenGrantHandler(
            $clientAuthService,
            $refreshTokenService,
            $accessTokenService
        );

        $this->assertTrue($handler->supports('refresh_token'));
        $this->assertFalse($handler->supports('authorization_code'));
        $this->assertFalse($handler->supports('client_credentials'));
    }

    public function testHandleSuccessfullyWithSameScopes(): void
    {
        $request = new TokenRequest(
            grantType: 'refresh_token',
            refreshToken: 'old_refresh_token',
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['authorization_code', 'refresh_token']);

        $user = new User('test@example.com', 'testuser', 'password');

        $oldRefreshToken = new RefreshToken('old_refresh_token', $client, $user, new DateTimeImmutable('+30 days'));
        $oldRefreshToken->setScopes(['read', 'write', 'offline_access']);

        $newAccessToken = new AccessToken('new_access_token', $client, new DateTimeImmutable('+1 hour'));
        $newAccessToken->setUser($user);
        $newAccessToken->setScopes(['read', 'write', 'offline_access']);

        $newRefreshToken = new RefreshToken('new_refresh_token', $client, $user, new DateTimeImmutable('+30 days'));
        $newRefreshToken->setScopes(['read', 'write', 'offline_access']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->with(null, 'test_client', 'test_secret')
            ->willReturn($client);

        $refreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $refreshTokenService->expects($this->once())
            ->method('validateAndConsumeToken')
            ->with('old_refresh_token', $client)
            ->willReturn($oldRefreshToken);
        $refreshTokenService->expects($this->once())
            ->method('createRefreshToken')
            ->with($client, $user, ['read', 'write', 'offline_access'])
            ->willReturn($newRefreshToken);

        $accessTokenService = $this->createMock(AccessTokenServiceInterface::class);
        $accessTokenService->expects($this->once())
            ->method('createAccessToken')
            ->with($client, ['read', 'write', 'offline_access'], $user)
            ->willReturn($newAccessToken);

        $handler = new RefreshTokenGrantHandler(
            $clientAuthService,
            $refreshTokenService,
            $accessTokenService
        );

        $response = $handler->handle($request);

        $this->assertSame('new_access_token', $response->accessToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertSame('new_refresh_token', $response->refreshToken);
        $this->assertSame('read write offline_access', $response->scope);
    }

    public function testHandleSuccessfullyWithNarrowedScopes(): void
    {
        $request = new TokenRequest(
            grantType: 'refresh_token',
            refreshToken: 'old_refresh_token',
            clientId: 'test_client',
            clientSecret: 'test_secret',
            scope: 'read'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['authorization_code', 'refresh_token']);

        $user = new User('test@example.com', 'testuser', 'password');

        $oldRefreshToken = new RefreshToken('old_refresh_token', $client, $user, new DateTimeImmutable('+30 days'));
        $oldRefreshToken->setScopes(['read', 'write', 'offline_access']);

        $newAccessToken = new AccessToken('new_access_token', $client, new DateTimeImmutable('+1 hour'));
        $newRefreshToken = new RefreshToken('new_refresh_token', $client, $user, new DateTimeImmutable('+30 days'));

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $refreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $refreshTokenService->expects($this->once())
            ->method('validateAndConsumeToken')
            ->willReturn($oldRefreshToken);
        $refreshTokenService->expects($this->once())
            ->method('createRefreshToken')
            ->with($client, $user, ['read'])
            ->willReturn($newRefreshToken);

        $accessTokenService = $this->createMock(AccessTokenServiceInterface::class);
        $accessTokenService->expects($this->once())
            ->method('createAccessToken')
            ->with($client, ['read'], $user)
            ->willReturn($newAccessToken);

        $handler = new RefreshTokenGrantHandler(
            $clientAuthService,
            $refreshTokenService,
            $accessTokenService
        );

        $response = $handler->handle($request);

        $this->assertSame('new_access_token', $response->accessToken);
        $this->assertSame('read', $response->scope);
    }

    public function testHandleThrowsExceptionWhenClientNotAuthorizedForGrantType(): void
    {
        $request = new TokenRequest(
            grantType: 'refresh_token',
            refreshToken: 'old_refresh_token',
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['client_credentials']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $refreshTokenService = $this->createStub(RefreshTokenServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);

        $handler = new RefreshTokenGrantHandler(
            $clientAuthService,
            $refreshTokenService,
            $accessTokenService
        );

        $this->expectException(UnauthorizedClientException::class);
        $this->expectExceptionMessage('Client is not authorized to use refresh_token grant type');

        $handler->handle($request);
    }

    public function testHandleThrowsExceptionWhenRefreshTokenMissing(): void
    {
        $request = new TokenRequest(
            grantType: 'refresh_token',
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['refresh_token']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $refreshTokenService = $this->createStub(RefreshTokenServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);

        $handler = new RefreshTokenGrantHandler(
            $clientAuthService,
            $refreshTokenService,
            $accessTokenService
        );

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Refresh token is required');

        $handler->handle($request);
    }

    public function testHandleThrowsExceptionWhenRequestedScopesExceedOriginal(): void
    {
        $request = new TokenRequest(
            grantType: 'refresh_token',
            refreshToken: 'old_refresh_token',
            clientId: 'test_client',
            clientSecret: 'test_secret',
            scope: 'read write admin'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes(['refresh_token']);

        $user = new User('test@example.com', 'testuser', 'password');

        $oldRefreshToken = new RefreshToken('old_refresh_token', $client, $user, new DateTimeImmutable('+30 days'));
        $oldRefreshToken->setScopes(['read', 'write']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $refreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $refreshTokenService->expects($this->once())
            ->method('validateAndConsumeToken')
            ->willReturn($oldRefreshToken);

        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);

        $handler = new RefreshTokenGrantHandler(
            $clientAuthService,
            $refreshTokenService,
            $accessTokenService
        );

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('Requested scopes cannot exceed original grant: admin');

        $handler->handle($request);
    }
}
