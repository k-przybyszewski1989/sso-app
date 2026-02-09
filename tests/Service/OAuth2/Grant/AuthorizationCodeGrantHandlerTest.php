<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2\Grant;

use App\Entity\AccessToken;
use App\Entity\AuthorizationCode;
use App\Entity\OAuth2Client;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\GrantType;
use App\Exception\OAuth2\InvalidRequestException;
use App\Exception\OAuth2\UnauthorizedClientException;
use App\Request\OAuth2\TokenRequest;
use App\Service\OAuth2\AccessTokenServiceInterface;
use App\Service\OAuth2\AuthorizationCodeServiceInterface;
use App\Service\OAuth2\ClientAuthenticationServiceInterface;
use App\Service\OAuth2\Grant\AuthorizationCodeGrantHandler;
use App\Service\OAuth2\RefreshTokenServiceInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthorizationCodeGrantHandlerTest extends TestCase
{
    public function testSupportsAuthorizationCodeGrantType(): void
    {
        $clientAuthService = $this->createStub(ClientAuthenticationServiceInterface::class);
        $authCodeService = $this->createStub(AuthorizationCodeServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);
        $refreshTokenService = $this->createStub(RefreshTokenServiceInterface::class);

        $handler = new AuthorizationCodeGrantHandler(
            $clientAuthService,
            $authCodeService,
            $accessTokenService,
            $refreshTokenService
        );

        $this->assertTrue($handler->supports('authorization_code'));
        $this->assertFalse($handler->supports('client_credentials'));
        $this->assertFalse($handler->supports('refresh_token'));
    }

    public function testHandleSuccessfullyWithoutOfflineAccess(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::AUTHORIZATION_CODE,
            code: 'auth_code_value',
            redirectUri: 'https://example.com/callback',
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes([GrantType::AUTHORIZATION_CODE]);

        $user = new User('test@example.com', 'testuser', 'password');
        $expiresAt = new DateTimeImmutable('+10 minutes');

        $authCode = new AuthorizationCode('auth_code_value', $client, $user, 'https://example.com/callback', $expiresAt);
        $authCode->setScopes(['read', 'write']);

        $accessToken = new AccessToken('generated_access_token', $client, new DateTimeImmutable('+1 hour'));
        $accessToken->setUser($user);
        $accessToken->setScopes(['read', 'write']);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->with(null, 'test_client', 'test_secret')
            ->willReturn($client);

        $authCodeService = $this->createMock(AuthorizationCodeServiceInterface::class);
        $authCodeService->expects($this->once())
            ->method('validateAndConsumeCode')
            ->with('auth_code_value', $client, 'https://example.com/callback', null)
            ->willReturn($authCode);

        $accessTokenService = $this->createMock(AccessTokenServiceInterface::class);
        $accessTokenService->expects($this->once())
            ->method('createAccessToken')
            ->with($client, ['read', 'write'], $user)
            ->willReturn($accessToken);

        $refreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $refreshTokenService->expects($this->never())
            ->method('createRefreshToken');

        $handler = new AuthorizationCodeGrantHandler(
            $clientAuthService,
            $authCodeService,
            $accessTokenService,
            $refreshTokenService
        );

        $response = $handler->handle($request);

        $this->assertSame('generated_access_token', $response->accessToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertNull($response->refreshToken);
        $this->assertSame('read write', $response->scope);
    }

    public function testHandleSuccessfullyWithOfflineAccess(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::AUTHORIZATION_CODE,
            code: 'auth_code_value',
            redirectUri: 'https://example.com/callback',
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes([GrantType::AUTHORIZATION_CODE, GrantType::REFRESH_TOKEN]);

        $user = new User('test@example.com', 'testuser', 'password');
        $expiresAt = new DateTimeImmutable('+10 minutes');

        $authCode = new AuthorizationCode('auth_code_value', $client, $user, 'https://example.com/callback', $expiresAt);
        $authCode->setScopes(['read', 'write', 'offline_access']);

        $accessToken = new AccessToken('generated_access_token', $client, new DateTimeImmutable('+1 hour'));
        $accessToken->setUser($user);

        $refreshToken = new RefreshToken('generated_refresh_token', $client, $user, new DateTimeImmutable('+30 days'));

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $authCodeService = $this->createMock(AuthorizationCodeServiceInterface::class);
        $authCodeService->expects($this->once())
            ->method('validateAndConsumeCode')
            ->willReturn($authCode);

        $accessTokenService = $this->createMock(AccessTokenServiceInterface::class);
        $accessTokenService->expects($this->once())
            ->method('createAccessToken')
            ->willReturn($accessToken);

        $refreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $refreshTokenService->expects($this->once())
            ->method('createRefreshToken')
            ->with($client, $user, ['read', 'write', 'offline_access'])
            ->willReturn($refreshToken);

        $handler = new AuthorizationCodeGrantHandler(
            $clientAuthService,
            $authCodeService,
            $accessTokenService,
            $refreshTokenService
        );

        $response = $handler->handle($request);

        $this->assertSame('generated_access_token', $response->accessToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertSame('generated_refresh_token', $response->refreshToken);
        $this->assertSame('read write offline_access', $response->scope);
    }

    public function testHandleThrowsExceptionWhenClientNotAuthorizedForGrantType(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::AUTHORIZATION_CODE,
            code: 'auth_code_value',
            redirectUri: 'https://example.com/callback',
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes([GrantType::CLIENT_CREDENTIALS]);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $authCodeService = $this->createStub(AuthorizationCodeServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);
        $refreshTokenService = $this->createStub(RefreshTokenServiceInterface::class);

        $handler = new AuthorizationCodeGrantHandler(
            $clientAuthService,
            $authCodeService,
            $accessTokenService,
            $refreshTokenService
        );

        $this->expectException(UnauthorizedClientException::class);
        $this->expectExceptionMessage('Client is not authorized to use authorization_code grant type');

        $handler->handle($request);
    }

    public function testHandleThrowsExceptionWhenCodeMissing(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::AUTHORIZATION_CODE,
            redirectUri: 'https://example.com/callback',
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes([GrantType::AUTHORIZATION_CODE]);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $authCodeService = $this->createStub(AuthorizationCodeServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);
        $refreshTokenService = $this->createStub(RefreshTokenServiceInterface::class);

        $handler = new AuthorizationCodeGrantHandler(
            $clientAuthService,
            $authCodeService,
            $accessTokenService,
            $refreshTokenService
        );

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Authorization code is required');

        $handler->handle($request);
    }

    public function testHandleThrowsExceptionWhenRedirectUriMissing(): void
    {
        $request = new TokenRequest(
            grantType: GrantType::AUTHORIZATION_CODE,
            code: 'auth_code_value',
            clientId: 'test_client',
            clientSecret: 'test_secret'
        );

        $client = new OAuth2Client('test_client', 'hashed_secret', 'Test Client');
        $client->setGrantTypes([GrantType::AUTHORIZATION_CODE]);

        $clientAuthService = $this->createMock(ClientAuthenticationServiceInterface::class);
        $clientAuthService->expects($this->once())
            ->method('authenticate')
            ->willReturn($client);

        $authCodeService = $this->createStub(AuthorizationCodeServiceInterface::class);
        $accessTokenService = $this->createStub(AccessTokenServiceInterface::class);
        $refreshTokenService = $this->createStub(RefreshTokenServiceInterface::class);

        $handler = new AuthorizationCodeGrantHandler(
            $clientAuthService,
            $authCodeService,
            $accessTokenService,
            $refreshTokenService
        );

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Redirect URI is required');

        $handler->handle($request);
    }
}
