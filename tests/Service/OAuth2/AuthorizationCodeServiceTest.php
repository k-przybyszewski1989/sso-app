<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Entity\AuthorizationCode;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\Exception\OAuth2\InvalidGrantException;
use App\Exception\OAuth2\InvalidRequestException;
use App\Repository\AuthorizationCodeRepositoryInterface;
use App\Service\OAuth2\AuthorizationCodeService;
use App\Service\OAuth2\PkceServiceInterface;
use App\Service\OAuth2\TokenGeneratorServiceInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuthorizationCodeServiceTest extends TestCase
{
    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    public function testCreateAuthorizationCodeWithoutPkce(): void
    {
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $redirectUri = 'https://example.com/callback';
        $scopes = ['read', 'write'];
        $generatedCode = 'generated_auth_code';

        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $tokenGenerator->expects($this->once())
            ->method('generateAuthorizationCode')
            ->willReturn($generatedCode);

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AuthorizationCode $code) use ($generatedCode, $scopes, $redirectUri) {
                return $code->getCode() === $generatedCode
                    && $code->getScopes() === $scopes
                    && $code->getRedirectUri() === $redirectUri
                    && null === $code->getCodeChallenge();
            }));

        $pkceService = $this->createStub(PkceServiceInterface::class);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);
        $code = $service->createAuthorizationCode($client, $user, $redirectUri, $scopes);

        $this->assertInstanceOf(AuthorizationCode::class, $code);
        $this->assertSame($generatedCode, $code->getCode());
        $this->assertSame($scopes, $code->getScopes());
        $this->assertNull($code->getCodeChallenge());
    }

    public function testCreateAuthorizationCodeWithPkce(): void
    {
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $redirectUri = 'https://example.com/callback';
        $scopes = ['read', 'write'];
        $generatedCode = 'generated_auth_code';
        $codeChallenge = 'challenge_value';
        $codeChallengeMethod = 'S256';

        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $tokenGenerator->expects($this->once())
            ->method('generateAuthorizationCode')
            ->willReturn($generatedCode);

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AuthorizationCode $code) use ($codeChallenge, $codeChallengeMethod) {
                return $code->getCodeChallenge() === $codeChallenge
                    && $code->getCodeChallengeMethod() === $codeChallengeMethod;
            }));

        $pkceService = $this->createStub(PkceServiceInterface::class);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);
        $code = $service->createAuthorizationCode(
            $client,
            $user,
            $redirectUri,
            $scopes,
            $codeChallenge,
            $codeChallengeMethod
        );

        $this->assertSame($codeChallenge, $code->getCodeChallenge());
        $this->assertSame($codeChallengeMethod, $code->getCodeChallengeMethod());
    }

    public function testValidateAndConsumeCodeSuccessfully(): void
    {
        $codeString = 'valid_auth_code';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $this->setEntityId($client, 1);
        $user = new User('test@example.com', 'testuser', 'password');
        $redirectUri = 'https://example.com/callback';
        $expiresAt = new DateTimeImmutable('+10 minutes');

        $authCode = new AuthorizationCode($codeString, $client, $user, $redirectUri, $expiresAt);

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('findByCode')
            ->with($codeString)
            ->willReturn($authCode);
        $authCodeRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AuthorizationCode $code) {
                return $code->isUsed() && null !== $code->getUsedAt();
            }));

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);
        $pkceService = $this->createStub(PkceServiceInterface::class);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);
        $result = $service->validateAndConsumeCode($codeString, $client, $redirectUri);

        $this->assertSame($authCode, $result);
        $this->assertTrue($authCode->isUsed());
    }

    public function testValidateAndConsumeCodeWithPkceSuccessfully(): void
    {
        $codeString = 'valid_auth_code';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $this->setEntityId($client, 1);
        $user = new User('test@example.com', 'testuser', 'password');
        $redirectUri = 'https://example.com/callback';
        $expiresAt = new DateTimeImmutable('+10 minutes');
        $codeChallenge = 'challenge_value';
        $codeChallengeMethod = 'S256';
        $codeVerifier = 'verifier_value';

        $authCode = new AuthorizationCode($codeString, $client, $user, $redirectUri, $expiresAt);
        $authCode->setCodeChallenge($codeChallenge);
        $authCode->setCodeChallengeMethod($codeChallengeMethod);

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('findByCode')
            ->with($codeString)
            ->willReturn($authCode);
        $authCodeRepository->expects($this->once())
            ->method('save');

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);

        $pkceService = $this->createMock(PkceServiceInterface::class);
        $pkceService->expects($this->once())
            ->method('validate')
            ->with($codeVerifier, $codeChallenge, $codeChallengeMethod)
            ->willReturn(true);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);
        $result = $service->validateAndConsumeCode($codeString, $client, $redirectUri, $codeVerifier);

        $this->assertSame($authCode, $result);
    }

    public function testValidateAndConsumeCodeThrowsExceptionWhenCodeNotFound(): void
    {
        $codeString = 'non_existent_code';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $redirectUri = 'https://example.com/callback';

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('findByCode')
            ->with($codeString)
            ->willReturn(null);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);
        $pkceService = $this->createStub(PkceServiceInterface::class);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('Invalid authorization code');

        $service->validateAndConsumeCode($codeString, $client, $redirectUri);
    }

    public function testValidateAndConsumeCodeThrowsExceptionWhenCodeExpired(): void
    {
        $codeString = 'expired_code';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $user = new User('test@example.com', 'testuser', 'password');
        $redirectUri = 'https://example.com/callback';
        $expiresAt = new DateTimeImmutable('-1 minute');

        $authCode = new AuthorizationCode($codeString, $client, $user, $redirectUri, $expiresAt);

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('findByCode')
            ->with($codeString)
            ->willReturn($authCode);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);
        $pkceService = $this->createStub(PkceServiceInterface::class);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('Authorization code is expired or has been used');

        $service->validateAndConsumeCode($codeString, $client, $redirectUri);
    }

    public function testValidateAndConsumeCodeThrowsExceptionWhenClientMismatch(): void
    {
        $codeString = 'valid_code';
        $client1 = new OAuth2Client('client_id_1', 'secret', 'Client 1');
        $this->setEntityId($client1, 1);
        $client2 = new OAuth2Client('client_id_2', 'secret', 'Client 2');
        $this->setEntityId($client2, 2);
        $user = new User('test@example.com', 'testuser', 'password');
        $redirectUri = 'https://example.com/callback';
        $expiresAt = new DateTimeImmutable('+10 minutes');

        $authCode = new AuthorizationCode($codeString, $client1, $user, $redirectUri, $expiresAt);

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('findByCode')
            ->with($codeString)
            ->willReturn($authCode);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);
        $pkceService = $this->createStub(PkceServiceInterface::class);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('Authorization code does not belong to this client');

        $service->validateAndConsumeCode($codeString, $client2, $redirectUri);
    }

    public function testValidateAndConsumeCodeThrowsExceptionWhenRedirectUriMismatch(): void
    {
        $codeString = 'valid_code';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $this->setEntityId($client, 1);
        $user = new User('test@example.com', 'testuser', 'password');
        $redirectUri = 'https://example.com/callback';
        $expiresAt = new DateTimeImmutable('+10 minutes');

        $authCode = new AuthorizationCode($codeString, $client, $user, $redirectUri, $expiresAt);

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('findByCode')
            ->with($codeString)
            ->willReturn($authCode);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);
        $pkceService = $this->createStub(PkceServiceInterface::class);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);

        $this->expectException(InvalidGrantException::class);
        $this->expectExceptionMessage('Redirect URI mismatch');

        $service->validateAndConsumeCode($codeString, $client, 'https://different.com/callback');
    }

    public function testValidateAndConsumeCodeThrowsExceptionWhenPkceVerifierMissing(): void
    {
        $codeString = 'valid_code';
        $client = new OAuth2Client('client_id', 'secret', 'Test Client');
        $this->setEntityId($client, 1);
        $user = new User('test@example.com', 'testuser', 'password');
        $redirectUri = 'https://example.com/callback';
        $expiresAt = new DateTimeImmutable('+10 minutes');

        $authCode = new AuthorizationCode($codeString, $client, $user, $redirectUri, $expiresAt);
        $authCode->setCodeChallenge('challenge_value');

        $authCodeRepository = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authCodeRepository->expects($this->once())
            ->method('findByCode')
            ->with($codeString)
            ->willReturn($authCode);

        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);
        $pkceService = $this->createStub(PkceServiceInterface::class);

        $service = new AuthorizationCodeService($authCodeRepository, $tokenGenerator, $pkceService);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Code verifier required for PKCE');

        $service->validateAndConsumeCode($codeString, $client, $redirectUri);
    }
}
