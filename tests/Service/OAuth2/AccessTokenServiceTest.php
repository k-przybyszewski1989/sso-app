<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\Exception\OAuth2\InvalidTokenException;
use App\Repository\AccessTokenRepositoryInterface;
use App\Service\OAuth2\AccessTokenService;
use App\Service\OAuth2\TokenGeneratorServiceInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AccessTokenServiceTest extends TestCase
{
    public function testCreateAccessTokenWithoutUser(): void
    {
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $scopes = ['read', 'write'];
        $generatedToken = 'generated_access_token';

        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $tokenGenerator->expects($this->once())
            ->method('generateAccessToken')
            ->willReturn($generatedToken);

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AccessToken $token) use ($generatedToken, $scopes) {
                return $token->getToken() === $generatedToken
                    && $token->getScopes() === $scopes
                    && null === $token->getUser();
            }));

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);
        $token = $service->createAccessToken($client, $scopes);

        $this->assertInstanceOf(AccessToken::class, $token);
        $this->assertSame($generatedToken, $token->getToken());
        $this->assertSame($scopes, $token->getScopes());
        $this->assertNull($token->getUser());
    }

    public function testCreateAccessTokenWithUser(): void
    {
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $scopes = ['read', 'write'];
        $generatedToken = 'generated_access_token';

        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $tokenGenerator->expects($this->once())
            ->method('generateAccessToken')
            ->willReturn($generatedToken);

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(AccessToken::class));

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);
        $token = $service->createAccessToken($client, $scopes, $user);

        $this->assertInstanceOf(AccessToken::class, $token);
        $this->assertSame($generatedToken, $token->getToken());
        $this->assertSame($scopes, $token->getScopes());
        $this->assertSame($user, $token->getUser());
    }

    public function testValidateTokenSuccessfully(): void
    {
        $tokenString = 'valid_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $expiresAt = new DateTimeImmutable('+1 hour');

        $accessToken = new AccessToken($tokenString, $client, $expiresAt);

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($accessToken);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);
        $result = $service->validateToken($tokenString);

        $this->assertSame($accessToken, $result);
    }

    public function testValidateTokenThrowsExceptionWhenTokenNotFound(): void
    {
        $tokenString = 'non_existent_token';

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn(null);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Invalid access token');

        $service->validateToken($tokenString);
    }

    public function testValidateTokenThrowsExceptionWhenTokenExpired(): void
    {
        $tokenString = 'expired_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $expiresAt = new DateTimeImmutable('-1 hour');

        $accessToken = new AccessToken($tokenString, $client, $expiresAt);

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($accessToken);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Access token is expired or revoked');

        $service->validateToken($tokenString);
    }

    public function testValidateTokenThrowsExceptionWhenTokenRevoked(): void
    {
        $tokenString = 'revoked_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $expiresAt = new DateTimeImmutable('+1 hour');

        $accessToken = new AccessToken($tokenString, $client, $expiresAt);
        $accessToken->setRevoked(true);

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($accessToken);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Access token is expired or revoked');

        $service->validateToken($tokenString);
    }

    public function testRevokeTokenSuccessfully(): void
    {
        $tokenString = 'valid_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $expiresAt = new DateTimeImmutable('+1 hour');

        $accessToken = new AccessToken($tokenString, $client, $expiresAt);

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($accessToken);
        $accessTokenRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AccessToken $token) {
                return $token->isRevoked() && null !== $token->getRevokedAt();
            }));

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);
        $service->revokeToken($tokenString);

        $this->assertTrue($accessToken->isRevoked());
    }

    public function testRevokeTokenSilentlySucceedsWhenTokenNotFound(): void
    {
        $tokenString = 'non_existent_token';

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn(null);
        $accessTokenRepository->expects($this->never())
            ->method('save');

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);
        $service->revokeToken($tokenString);

        $this->addToAssertionCount(1);
    }

    public function testRevokeTokenSilentlySucceedsWhenAlreadyRevoked(): void
    {
        $tokenString = 'already_revoked_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $expiresAt = new DateTimeImmutable('+1 hour');

        $accessToken = new AccessToken($tokenString, $client, $expiresAt);
        $accessToken->setRevoked(true);

        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($accessToken);
        $accessTokenRepository->expects($this->never())
            ->method('save');

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new AccessTokenService($accessTokenRepository, $tokenGenerator);
        $service->revokeToken($tokenString);

        $this->addToAssertionCount(1);
    }
}
