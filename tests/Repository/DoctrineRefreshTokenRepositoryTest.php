<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\OAuth2Client;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Repository\RefreshTokenRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRefreshTokenRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private RefreshTokenRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(RefreshTokenRepositoryInterface::class);

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
        $user = new User('refreshtoken_save@example.com', 'testuser_refreshsave', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+30 days');
        $token = new RefreshToken('refresh_token_123', $client, $user, $expiresAt);
        $this->repository->save($token);
        $this->entityManager->flush();

        $foundToken = $this->repository->findByToken('refresh_token_123');

        $this->assertNotNull($foundToken);
        $this->assertSame($token->getId(), $foundToken->getId());
        $this->assertSame('refresh_token_123', $foundToken->getToken());
    }

    public function testFindByTokenReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByToken('nonexistent_token');

        $this->assertNull($result);
    }

    public function testGetByToken(): void
    {
        $client = new OAuth2Client('client_456', 'secret', 'Test Client');
        $user = new User('user2@example.com', 'testuser2', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+30 days');
        $token = new RefreshToken('get_refresh_token', $client, $user, $expiresAt);
        $this->repository->save($token);
        $this->entityManager->flush();

        $foundToken = $this->repository->getByToken('get_refresh_token');

        $this->assertSame($token->getId(), $foundToken->getId());
        $this->assertSame('get_refresh_token', $foundToken->getToken());
    }

    public function testGetByTokenThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('RefreshToken not found: missing_token');

        $this->repository->getByToken('missing_token');
    }

    public function testFindByUser(): void
    {
        $client = new OAuth2Client('client_789', 'secret', 'Test Client');
        $user = new User('refreshtoken_findbyuser@example.com', 'testuser_refreshfindbyuser', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+30 days');
        $token1 = new RefreshToken('token_1', $client, $user, $expiresAt);
        $token2 = new RefreshToken('token_2', $client, $user, $expiresAt);

        $this->repository->save($token1);
        $this->repository->save($token2);
        $this->entityManager->flush();

        $tokens = $this->repository->findByUser($user);

        $this->assertCount(2, $tokens);
        $this->assertContainsOnlyInstancesOf(RefreshToken::class, $tokens);
    }

    public function testDelete(): void
    {
        $client = new OAuth2Client('client_del', 'secret', 'Test Client');
        $user = new User('user4@example.com', 'testuser4', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+30 days');
        $token = new RefreshToken('delete_token', $client, $user, $expiresAt);
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
        $user = new User('user5@example.com', 'testuser5', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiredToken = new RefreshToken('expired_token', $client, $user, new DateTimeImmutable('-1 day'));
        $validToken = new RefreshToken('valid_token', $client, $user, new DateTimeImmutable('+30 days'));

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
        $user1 = new User('user6@example.com', 'user6', 'password');
        $user2 = new User('user7@example.com', 'user7', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);

        $expiresAt = new DateTimeImmutable('+30 days');
        $token1 = new RefreshToken('user1_token_1', $client, $user1, $expiresAt);
        $token2 = new RefreshToken('user1_token_2', $client, $user1, $expiresAt);
        $token3 = new RefreshToken('user2_token', $client, $user2, $expiresAt);

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

    public function testGetByTokenWithLock(): void
    {
        $client = new OAuth2Client('lock_client', 'secret', 'Test Client');
        $user = new User('user8@example.com', 'user8', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+30 days');
        $token = new RefreshToken('lock_token', $client, $user, $expiresAt);
        $this->repository->save($token);
        $this->entityManager->flush();

        $foundToken = $this->repository->getByToken('lock_token', true);

        $this->assertSame($token->getId(), $foundToken->getId());
    }
}
