<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Entity\OAuth2Client;
use App\Enum\GrantType;
use App\Exception\EntityNotFoundException;
use App\Repository\OAuth2ClientRepositoryInterface;
use App\Service\OAuth2\ClientManagementService;
use App\Service\OAuth2\TokenGeneratorServiceInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AllowMockObjectsWithoutExpectations]
final class ClientManagementServiceTest extends TestCase
{
    public function testCreateClientGeneratesUniqueClientIdAndSecret(): void
    {
        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $tokenGenerator->expects($this->once())
            ->method('generateClientId')
            ->willReturn('test-client-id-123');

        $tokenGenerator->expects($this->once())
            ->method('generateClientSecret')
            ->willReturn('test-secret-456');

        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with(
                $this->callback(fn ($client) => $client instanceof OAuth2Client),
                'test-secret-456'
            )
            ->willReturn('hashed-secret');

        $clientRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (OAuth2Client $client) {
                $this->assertEquals('test-client-id-123', $client->getClientId());
                $this->assertEquals('hashed-secret', $client->getClientSecretHash());
                $this->assertEquals('Test Client', $client->getName());

                return true;
            }));

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'OAuth2 client created successfully',
                $this->callback(function (array $context) {
                    return 'test-client-id-123' === $context['clientId']
                        && 'Test Client' === $context['name']
                        && true === $context['confidential']
                        && $context['grantTypes'] === ['authorization_code'];
                })
            );

        $service = new ClientManagementService(
            $clientRepository,
            $tokenGenerator,
            $passwordHasher,
            $logger
        );

        $result = $service->createClient(
            'Test Client',
            ['https://example.com/callback'],
            [GrantType::AUTHORIZATION_CODE],
            true
        );

        $this->assertArrayHasKey('client_id', $result);
        $this->assertArrayHasKey('client_secret', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('test-client-id-123', $result['client_id']);
        $this->assertEquals('test-secret-456', $result['client_secret']);
        $this->assertEquals('Test Client', $result['name']);
    }

    public function testCreateClientSetsAllPropertiesCorrectly(): void
    {
        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $tokenGenerator->method('generateClientId')->willReturn('client-id');
        $tokenGenerator->method('generateClientSecret')->willReturn('client-secret');
        $passwordHasher->method('hashPassword')->willReturn('hashed');

        $redirectUris = ['https://app.example.com/callback', 'https://app.example.com/redirect'];
        $grantTypes = [GrantType::AUTHORIZATION_CODE, GrantType::REFRESH_TOKEN];

        $clientRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (OAuth2Client $client) use ($redirectUris, $grantTypes) {
                $this->assertEquals($redirectUris, $client->getRedirectUris());
                $this->assertEquals($grantTypes, $client->getGrantTypes());
                $this->assertFalse($client->isConfidential());

                return true;
            }));

        $logger->method('info');

        $service = new ClientManagementService(
            $clientRepository,
            $tokenGenerator,
            $passwordHasher,
            $logger
        );

        $service->createClient(
            'Public Client',
            $redirectUris,
            $grantTypes,
            false
        );
    }

    public function testListClientsReturnsAllClients(): void
    {
        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $tokenGenerator = $this->createStub(TokenGeneratorServiceInterface::class);
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $client1 = $this->createStub(OAuth2Client::class);
        $client2 = $this->createStub(OAuth2Client::class);
        $expectedClients = [$client1, $client2];

        $clientRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($expectedClients);

        $service = new ClientManagementService(
            $clientRepository,
            $tokenGenerator,
            $passwordHasher,
            $logger
        );

        $result = $service->listClients();

        $this->assertSame($expectedClients, $result);
    }

    public function testDeleteClientSuccessfully(): void
    {
        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $client = $this->createMock(OAuth2Client::class);
        $client->method('getName')->willReturn('Test Client');

        $clientRepository->expects($this->once())
            ->method('getByClientId')
            ->with('client-id-123')
            ->willReturn($client);

        $clientRepository->expects($this->once())
            ->method('delete')
            ->with($client);

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'OAuth2 client deleted successfully',
                $this->callback(function (array $context) {
                    return 'client-id-123' === $context['clientId']
                        && 'Test Client' === $context['name'];
                })
            );

        $service = new ClientManagementService(
            $clientRepository,
            $tokenGenerator,
            $passwordHasher,
            $logger
        );

        $service->deleteClient('client-id-123');
    }

    public function testDeleteClientThrowsEntityNotFoundExceptionWhenNotExists(): void
    {
        $clientRepository = $this->createMock(OAuth2ClientRepositoryInterface::class);
        $tokenGenerator = $this->createMock(TokenGeneratorServiceInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $exception = new EntityNotFoundException('OAuth2Client', 'nonexistent-client');

        $clientRepository->expects($this->once())
            ->method('getByClientId')
            ->with('nonexistent-client')
            ->willThrowException($exception);

        $clientRepository->expects($this->never())
            ->method('delete');

        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Attempted to delete non-existent OAuth2 client',
                $this->callback(function (array $context) {
                    return 'nonexistent-client' === $context['clientId'];
                })
            );

        $service = new ClientManagementService(
            $clientRepository,
            $tokenGenerator,
            $passwordHasher,
            $logger
        );

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Client not found');

        $service->deleteClient('nonexistent-client');
    }
}
