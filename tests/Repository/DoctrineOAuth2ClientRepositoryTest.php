<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\OAuth2Client;
use App\Exception\EntityNotFoundException;
use App\Repository\OAuth2ClientRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineOAuth2ClientRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private OAuth2ClientRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(OAuth2ClientRepositoryInterface::class);

        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testSaveAndFindById(): void
    {
        $client = new OAuth2Client('client_123', 'secret_hash', 'Test Client');
        $this->repository->save($client);
        $this->entityManager->flush();

        $foundClient = $this->repository->findById($client->getId());

        $this->assertNotNull($foundClient);
        $this->assertSame($client->getId(), $foundClient->getId());
        $this->assertSame('client_123', $foundClient->getClientId());
        $this->assertSame('Test Client', $foundClient->getName());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findById(999999);

        $this->assertNull($result);
    }

    public function testGetById(): void
    {
        $client = new OAuth2Client('client_456', 'secret_hash', 'Another Client');
        $this->repository->save($client);
        $this->entityManager->flush();

        $foundClient = $this->repository->getById($client->getId());

        $this->assertSame($client->getId(), $foundClient->getId());
        $this->assertSame('client_456', $foundClient->getClientId());
    }

    public function testGetByIdThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('OAuth2Client not found: 999999');

        $this->repository->getById(999999);
    }

    public function testFindByClientId(): void
    {
        $client = new OAuth2Client('unique_client', 'secret_hash', 'Unique Client');
        $this->repository->save($client);
        $this->entityManager->flush();

        $foundClient = $this->repository->findByClientId('unique_client');

        $this->assertNotNull($foundClient);
        $this->assertSame($client->getId(), $foundClient->getId());
        $this->assertSame('unique_client', $foundClient->getClientId());
    }

    public function testFindByClientIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByClientId('nonexistent_client');

        $this->assertNull($result);
    }

    public function testGetByClientId(): void
    {
        $client = new OAuth2Client('get_client', 'secret_hash', 'Get Client');
        $this->repository->save($client);
        $this->entityManager->flush();

        $foundClient = $this->repository->getByClientId('get_client');

        $this->assertSame($client->getId(), $foundClient->getId());
        $this->assertSame('get_client', $foundClient->getClientId());
    }

    public function testGetByClientIdThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('OAuth2Client not found: missing_client');

        $this->repository->getByClientId('missing_client');
    }

    public function testFindAll(): void
    {
        $client1 = new OAuth2Client('client_1', 'secret_hash_1', 'Client 1');
        $client2 = new OAuth2Client('client_2', 'secret_hash_2', 'Client 2');

        $this->repository->save($client1);
        $this->repository->save($client2);
        $this->entityManager->flush();

        $clients = $this->repository->findAll();

        $this->assertCount(2, $clients);
        $this->assertContainsOnlyInstancesOf(OAuth2Client::class, $clients);
    }

    public function testFindActive(): void
    {
        $activeClient = new OAuth2Client('active_client', 'secret_hash', 'Active Client');
        $activeClient->setActive(true);

        $inactiveClient = new OAuth2Client('inactive_client', 'secret_hash_2', 'Inactive Client');
        $inactiveClient->setActive(false);

        $this->repository->save($activeClient);
        $this->repository->save($inactiveClient);
        $this->entityManager->flush();

        $activeClients = $this->repository->findActive();

        $this->assertCount(1, $activeClients);
        $this->assertTrue($activeClients[0]->isActive());
        $this->assertSame('active_client', $activeClients[0]->getClientId());
    }

    public function testDelete(): void
    {
        $client = new OAuth2Client('delete_client', 'secret_hash', 'Delete Client');
        $this->repository->save($client);
        $this->entityManager->flush();
        $clientId = $client->getId();

        $this->repository->delete($client);
        $this->entityManager->flush();

        $result = $this->repository->findById($clientId);
        $this->assertNull($result);
    }

    public function testGetByClientIdWithLock(): void
    {
        $client = new OAuth2Client('lock_client', 'secret_hash', 'Lock Client');
        $this->repository->save($client);
        $this->entityManager->flush();

        $foundClient = $this->repository->getByClientId('lock_client', true);

        $this->assertSame($client->getId(), $foundClient->getId());
    }
}
