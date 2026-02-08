<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Entity\OAuth2Client;
use App\Exception\OAuth2\InvalidClientException;
use App\Repository\OAuth2ClientRepositoryInterface;
use App\Service\OAuth2\ClientAuthenticationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class ClientAuthenticationServiceTest extends TestCase
{
    public function testAuthenticateWithBasicAuthSuccessfully(): void
    {
        $clientId = 'test_client_id';
        $clientSecret = 'test_secret';
        $authHeader = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);

        $client = new OAuth2Client($clientId, 'hashed_secret', 'Test Client');
        $client->setActive(true);
        $client->setConfidential(true);

        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $clientRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('verify')
            ->with('hashed_secret', $clientSecret)
            ->willReturn(true);

        $passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $passwordHasherFactory->expects($this->once())
            ->method('getPasswordHasher')
            ->with($client)
            ->willReturn($passwordHasher);

        $service = new ClientAuthenticationService($clientRepository, $passwordHasherFactory);
        $result = $service->authenticate($authHeader, null, null);

        $this->assertSame($client, $result);
    }

    public function testAuthenticateWithPostBodyCredentialsSuccessfully(): void
    {
        $clientId = 'test_client_id';
        $clientSecret = 'test_secret';

        $client = new OAuth2Client($clientId, 'hashed_secret', 'Test Client');
        $client->setActive(true);
        $client->setConfidential(true);

        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $clientRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('verify')
            ->with('hashed_secret', $clientSecret)
            ->willReturn(true);

        $passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $passwordHasherFactory->expects($this->once())
            ->method('getPasswordHasher')
            ->with($client)
            ->willReturn($passwordHasher);

        $service = new ClientAuthenticationService($clientRepository, $passwordHasherFactory);
        $result = $service->authenticate(null, $clientId, $clientSecret);

        $this->assertSame($client, $result);
    }

    public function testAuthenticateThrowsExceptionWhenCredentialsMissing(): void
    {
        $clientRepository = $this->createStub(OAuth2ClientRepositoryInterface::class);
        $passwordHasherFactory = $this->createStub(PasswordHasherFactoryInterface::class);

        $service = new ClientAuthenticationService($clientRepository, $passwordHasherFactory);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Client authentication failed: missing credentials');

        $service->authenticate(null, null, null);
    }

    public function testAuthenticateThrowsExceptionWhenClientNotFound(): void
    {
        $clientId = 'non_existent_client';
        $clientSecret = 'test_secret';

        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $clientRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn(null);

        $passwordHasherFactory = $this->createStub(PasswordHasherFactoryInterface::class);

        $service = new ClientAuthenticationService($clientRepository, $passwordHasherFactory);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Client authentication failed: invalid client');

        $service->authenticate(null, $clientId, $clientSecret);
    }

    public function testAuthenticateThrowsExceptionWhenClientInactive(): void
    {
        $clientId = 'test_client_id';
        $clientSecret = 'test_secret';

        $client = new OAuth2Client($clientId, 'hashed_secret', 'Test Client');
        $client->setActive(false);

        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $clientRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $passwordHasherFactory = $this->createStub(PasswordHasherFactoryInterface::class);

        $service = new ClientAuthenticationService($clientRepository, $passwordHasherFactory);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Client authentication failed: client is inactive');

        $service->authenticate(null, $clientId, $clientSecret);
    }

    public function testAuthenticateThrowsExceptionWhenSecretInvalid(): void
    {
        $clientId = 'test_client_id';
        $clientSecret = 'wrong_secret';

        $client = new OAuth2Client($clientId, 'hashed_secret', 'Test Client');
        $client->setActive(true);
        $client->setConfidential(true);

        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $clientRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('verify')
            ->with('hashed_secret', $clientSecret)
            ->willReturn(false);

        $passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $passwordHasherFactory->expects($this->once())
            ->method('getPasswordHasher')
            ->with($client)
            ->willReturn($passwordHasher);

        $service = new ClientAuthenticationService($clientRepository, $passwordHasherFactory);

        $this->expectException(InvalidClientException::class);
        $this->expectExceptionMessage('Client authentication failed: invalid credentials');

        $service->authenticate(null, $clientId, $clientSecret);
    }

    public function testAuthenticateWithPublicClientSuccessfully(): void
    {
        $clientId = 'public_client_id';

        $client = new OAuth2Client($clientId, '', 'Public Client');
        $client->setActive(true);
        $client->setConfidential(false);

        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $clientRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($client);

        $passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $passwordHasherFactory->expects($this->never())
            ->method('getPasswordHasher');

        $service = new ClientAuthenticationService($clientRepository, $passwordHasherFactory);
        $result = $service->authenticate(null, $clientId, 'any_secret');

        $this->assertSame($client, $result);
    }
}
