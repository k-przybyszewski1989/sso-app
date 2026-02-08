<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Repository\AccessTokenRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineAccessTokenRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AccessTokenRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(AccessTokenRepositoryInterface::class);

        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        $this->entityManager->clear();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testSaveAndFindByToken(): void
    {
        $client = new OAuth2Client('client_123', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $token = new AccessToken('test_token_123', $client, $expiresAt);
        $this->repository->save($token);
        $this->entityManager->flush();

        $foundToken = $this->repository->findByToken('test_token_123');

        $this->assertNotNull($foundToken);
        $this->assertSame($token->getId(), $foundToken->getId());
        $this->assertSame('test_token_123', $foundToken->getToken());
    }

    public function testFindByTokenReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByToken('nonexistent_token');

        $this->assertNull($result);
    }

    public function testGetByToken(): void
    {
        $client = new OAuth2Client('client_456', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $token = new AccessToken('get_token_123', $client, $expiresAt);
        $this->repository->save($token);
        $this->entityManager->flush();

        $foundToken = $this->repository->getByToken('get_token_123');

        $this->assertSame($token->getId(), $foundToken->getId());
        $this->assertSame('get_token_123', $foundToken->getToken());
    }

    public function testGetByTokenThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('AccessToken not found: missing_token');

        $this->repository->getByToken('missing_token');
    }

    public function testFindByUser(): void
    {
        $client = new OAuth2Client('client_789', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $user = new User('findbyuser@example.com', 'testuser_findbyuser', 'password');
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $token1 = new AccessToken('token_1', $client, $expiresAt);
        $token1->setUser($user);
        $token2 = new AccessToken('token_2', $client, $expiresAt);
        $token2->setUser($user);

        $this->repository->save($token1);
        $this->repository->save($token2);
        $this->entityManager->flush();

        $tokens = $this->repository->findByUser($user);

        $this->assertCount(2, $tokens);
        $this->assertContainsOnlyInstancesOf(AccessToken::class, $tokens);
    }

    public function testFindByClient(): void
    {
        $client = new OAuth2Client('client_abc', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $token1 = new AccessToken('client_token_1', $client, $expiresAt);
        $token2 = new AccessToken('client_token_2', $client, $expiresAt);

        $this->repository->save($token1);
        $this->repository->save($token2);
        $this->entityManager->flush();

        $tokens = $this->repository->findByClient($client);

        $this->assertCount(2, $tokens);
        $this->assertContainsOnlyInstancesOf(AccessToken::class, $tokens);
    }

    public function testDelete(): void
    {
        $client = new OAuth2Client('client_del', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $token = new AccessToken('delete_token', $client, $expiresAt);
        $this->repository->save($token);
        $this->entityManager->flush();

        $this->repository->delete($token);
        $this->entityManager->flush();

        $result = $this->repository->findByToken('delete_token');
        $this->assertNull($result);
    }

    public function testDeleteExpired(): void
    {
        $client = new OAuth2Client('client_exp', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $expiredToken = new AccessToken('expired_token', $client, new DateTimeImmutable('-1 hour'));
        $validToken = new AccessToken('valid_token', $client, new DateTimeImmutable('+1 hour'));

        $this->repository->save($expiredToken);
        $this->repository->save($validToken);
        $this->entityManager->flush();

        $deletedCount = $this->repository->deleteExpired();
        $this->entityManager->flush();

        $this->assertSame(1, $deletedCount);
        $this->assertNull($this->repository->findByToken('expired_token'));
        $this->assertNotNull($this->repository->findByToken('valid_token'));
    }

    public function testRevokeAllForUser(): void
    {
        $client = new OAuth2Client('client_rev', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $user1 = new User('user1@example.com', 'user1', 'password');
        $user2 = new User('user2@example.com', 'user2', 'password');
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $token1 = new AccessToken('user1_token_1', $client, $expiresAt);
        $token1->setUser($user1);
        $token2 = new AccessToken('user1_token_2', $client, $expiresAt);
        $token2->setUser($user1);
        $token3 = new AccessToken('user2_token', $client, $expiresAt);
        $token3->setUser($user2);

        $this->repository->save($token1);
        $this->repository->save($token2);
        $this->repository->save($token3);
        $this->entityManager->flush();

        $revokedCount = $this->repository->revokeAllForUser($user1);
        $this->entityManager->flush();

        $this->assertSame(2, $revokedCount);

        $this->entityManager->refresh($token1);
        $this->entityManager->refresh($token2);
        $this->entityManager->refresh($token3);

        $this->assertTrue($token1->isRevoked());
        $this->assertTrue($token2->isRevoked());
        $this->assertFalse($token3->isRevoked());
    }

    public function testRevokeAllForClient(): void
    {
        $client1 = new OAuth2Client('client_1', 'secret', 'Client 1');
        $client2 = new OAuth2Client('client_2', 'secret', 'Client 2');
        $this->entityManager->persist($client1);
        $this->entityManager->persist($client2);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $token1 = new AccessToken('c1_token_1', $client1, $expiresAt);
        $token2 = new AccessToken('c1_token_2', $client1, $expiresAt);
        $token3 = new AccessToken('c2_token', $client2, $expiresAt);

        $this->repository->save($token1);
        $this->repository->save($token2);
        $this->repository->save($token3);
        $this->entityManager->flush();

        $revokedCount = $this->repository->revokeAllForClient($client1);
        $this->entityManager->flush();

        $this->assertSame(2, $revokedCount);

        $this->entityManager->refresh($token1);
        $this->entityManager->refresh($token2);
        $this->entityManager->refresh($token3);

        $this->assertTrue($token1->isRevoked());
        $this->assertTrue($token2->isRevoked());
        $this->assertFalse($token3->isRevoked());
    }

    public function testGetByTokenWithLock(): void
    {
        $client = new OAuth2Client('lock_client', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $expiresAt = new DateTimeImmutable('+1 hour');
        $token = new AccessToken('lock_token', $client, $expiresAt);
        $this->repository->save($token);
        $this->entityManager->flush();

        $foundToken = $this->repository->getByToken('lock_token', true);

        $this->assertSame($token->getId(), $foundToken->getId());
    }
}
