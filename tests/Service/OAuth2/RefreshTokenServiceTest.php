<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Entity\OAuth2Client;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\OAuth2\InvalidGrantException;
use App\Repository\RefreshTokenRepositoryInterface;
use App\Service\OAuth2\RefreshTokenService;
use App\Service\OAuth2\TokenGeneratorServiceInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RefreshTokenServiceTest extends TestCase
{
    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    public function testCreateRefreshTokenSuccessfully(): void
    {
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $scopes = ['read', 'write', 'offline_access'];
        $generatedToken = 'generated_refresh_token';

        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $tokenGenerator->expects($this->once())
            ->method('generateRefreshToken')
            ->willReturn($generatedToken);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RefreshToken $token) use ($generatedToken, $scopes) {
                return $token->getToken() === $generatedToken
                    && $token->getScopes() === $scopes;
            }));

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);
        $token = $service->createRefreshToken($client, $user, $scopes);

        $this->assertSame($generatedToken, $token->getToken());
        $this->assertSame($scopes, $token->getScopes());
        $this->assertSame($client, $token->getClient());
        $this->assertSame($user, $token->getUser());
    }

    public function testValidateAndConsumeTokenSuccessfully(): void
    {
        $tokenString = 'valid_refresh_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $this->setEntityId($client, 1);
        $user = new User('test@example.com', 'testuser', 'password');
        $expiresAt = new DateTimeImmutable('+30 days');

        $refreshToken = new RefreshToken($tokenString, $client, $user, $expiresAt);
        $refreshToken->setScopes(['read', 'write']);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($refreshToken);
        $refreshTokenRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RefreshToken $token) {
                return $token->isRevoked() && null !== $token->getRevokedAt();
            }));

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);
        $result = $service->validateAndConsumeToken($tokenString, $client);

        $this->assertSame($refreshToken, $result);
        $this->assertTrue($refreshToken->isRevoked());
    }

    public function testValidateAndConsumeTokenThrowsExceptionWhenTokenNotFound(): void
    {
        $tokenString = 'non_existent_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn(null);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('Invalid refresh token');

        $service->validateAndConsumeToken($tokenString, $client);
    }

    public function testValidateAndConsumeTokenThrowsExceptionWhenTokenExpired(): void
    {
        $tokenString = 'expired_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $expiresAt = new DateTimeImmutable('-1 day');

        $refreshToken = new RefreshToken($tokenString, $client, $user, $expiresAt);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($refreshToken);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('Refresh token is expired or revoked');

        $service->validateAndConsumeToken($tokenString, $client);
    }

    public function testValidateAndConsumeTokenThrowsExceptionWhenTokenRevoked(): void
    {
        $tokenString = 'revoked_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $expiresAt = new DateTimeImmutable('+30 days');

        $refreshToken = new RefreshToken($tokenString, $client, $user, $expiresAt);
        $refreshToken->setRevoked(true);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($refreshToken);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('Refresh token is expired or revoked');

        $service->validateAndConsumeToken($tokenString, $client);
    }

    public function testValidateAndConsumeTokenThrowsExceptionWhenClientMismatch(): void
    {
        $tokenString = 'valid_token';
        $client1 = new OAuth2Client('client_id_1', 'secret', 'Client 1');
        $this->setEntityId($client1, 1);
        $client2 = new OAuth2Client('client_id_2', 'secret', 'Client 2');
        $this->setEntityId($client2, 2);
        $user = new User('test@example.com', 'testuser', 'password');
        $expiresAt = new DateTimeImmutable('+30 days');

        $refreshToken = new RefreshToken($tokenString, $client1, $user, $expiresAt);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($refreshToken);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('Refresh token does not belong to this client');

        $service->validateAndConsumeToken($tokenString, $client2);
    }

    public function testRevokeTokenSuccessfully(): void
    {
        $tokenString = 'valid_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $expiresAt = new DateTimeImmutable('+30 days');

        $refreshToken = new RefreshToken($tokenString, $client, $user, $expiresAt);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($refreshToken);
        $refreshTokenRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RefreshToken $token) {
                return $token->isRevoked() && null !== $token->getRevokedAt();
            }));

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);
        $service->revokeToken($tokenString);

        $this->assertTrue($refreshToken->isRevoked());
    }

    public function testRevokeTokenSilentlySucceedsWhenTokenNotFound(): void
    {
        $tokenString = 'non_existent_token';

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn(null);
        $refreshTokenRepository->expects($this->never())
            ->method('save');

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);
        $service->revokeToken($tokenString);

        $this->addToAssertionCount(1);
    }

    public function testRevokeTokenSilentlySucceedsWhenAlreadyRevoked(): void
    {
        $tokenString = 'already_revoked_token';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $expiresAt = new DateTimeImmutable('+30 days');

        $refreshToken = new RefreshToken($tokenString, $client, $user, $expiresAt);
        $refreshToken->setRevoked(true);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($tokenString)
            ->willReturn($refreshToken);
        $refreshTokenRepository->expects($this->never())
            ->method('save');

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $service = new RefreshTokenService($refreshTokenRepository, $tokenGenerator);
        $service->revokeToken($tokenString);

        $this->addToAssertionCount(1);
    }
}
