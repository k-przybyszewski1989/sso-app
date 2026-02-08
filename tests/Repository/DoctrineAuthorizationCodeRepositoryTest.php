<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AuthorizationCode;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Repository\AuthorizationCodeRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineAuthorizationCodeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AuthorizationCodeRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(AuthorizationCodeRepositoryInterface::class);

        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testSaveAndFindByCode(): void
    {
        $client = new OAuth2Client('client_123', 'secret', 'Test Client');
        $user = new User('user@example.com', 'testuser', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+10 minutes');
        $code = new AuthorizationCode('auth_code_123', $client, $user, 'https://example.com/callback', $expiresAt);
        $this->repository->save($code);
        $this->entityManager->flush();

        $foundCode = $this->repository->findByCode('auth_code_123');

        $this->assertNotNull($foundCode);
        $this->assertSame($code->getId(), $foundCode->getId());
        $this->assertSame('auth_code_123', $foundCode->getCode());
    }

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByCode('nonexistent_code');

        $this->assertNull($result);
    }

    public function testGetByCode(): void
    {
        $client = new OAuth2Client('client_456', 'secret', 'Test Client');
        $user = new User('user2@example.com', 'testuser2', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+10 minutes');
        $code = new AuthorizationCode('get_auth_code', $client, $user, 'https://example.com/callback', $expiresAt);
        $this->repository->save($code);
        $this->entityManager->flush();

        $foundCode = $this->repository->getByCode('get_auth_code');

        $this->assertSame($code->getId(), $foundCode->getId());
        $this->assertSame('get_auth_code', $foundCode->getCode());
    }

    public function testGetByCodeThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('AuthorizationCode not found: missing_code');

        $this->repository->getByCode('missing_code');
    }

    public function testDelete(): void
    {
        $client = new OAuth2Client('client_del', 'secret', 'Test Client');
        $user = new User('user3@example.com', 'testuser3', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+10 minutes');
        $code = new AuthorizationCode('delete_code', $client, $user, 'https://example.com/callback', $expiresAt);
        $this->repository->save($code);
        $this->entityManager->flush();

        $this->repository->delete($code);
        $this->entityManager->flush();

        $result = $this->repository->findByCode('delete_code');
        $this->assertNull($result);
    }

    public function testDeleteExpired(): void
    {
        $client = new OAuth2Client('client_exp', 'secret', 'Test Client');
        $user = new User('user4@example.com', 'testuser4', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiredCode = new AuthorizationCode(
            'expired_code',
            $client,
            $user,
            'https://example.com/callback',
            new DateTimeImmutable('-5 minutes')
        );
        $validCode = new AuthorizationCode(
            'valid_code',
            $client,
            $user,
            'https://example.com/callback',
            new DateTimeImmutable('+10 minutes')
        );

        $this->repository->save($expiredCode);
        $this->repository->save($validCode);
        $this->entityManager->flush();

        $deletedCount = $this->repository->deleteExpired();
        $this->entityManager->flush();

        $this->assertSame(1, $deletedCount);
        $this->assertNull($this->repository->findByCode('expired_code'));
        $this->assertNotNull($this->repository->findByCode('valid_code'));
    }

    public function testGetByCodeWithLock(): void
    {
        $client = new OAuth2Client('lock_client', 'secret', 'Test Client');
        $user = new User('user5@example.com', 'user5', 'password');
        $this->entityManager->persist($client);
        $this->entityManager->persist($user);

        $expiresAt = new DateTimeImmutable('+10 minutes');
        $code = new AuthorizationCode('lock_code', $client, $user, 'https://example.com/callback', $expiresAt);
        $this->repository->save($code);
        $this->entityManager->flush();

        $foundCode = $this->repository->getByCode('lock_code', true);

        $this->assertSame($code->getId(), $foundCode->getId());
    }
}
