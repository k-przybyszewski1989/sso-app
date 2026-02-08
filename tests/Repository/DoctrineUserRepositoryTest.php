<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(UserRepositoryInterface::class);

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
        $user = new User('test@example.com', 'testuser', 'password');
        $this->repository->save($user);
        $this->entityManager->flush();

        $foundUser = $this->repository->findById($user->getId());

        $this->assertNotNull($foundUser);
        $this->assertSame($user->getId(), $foundUser->getId());
        $this->assertSame('test@example.com', $foundUser->getEmail());
        $this->assertSame('testuser', $foundUser->getUsername());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findById(999999);

        $this->assertNull($result);
    }

    public function testGetById(): void
    {
        $user = new User('test@example.com', 'testuser', 'password');
        $this->repository->save($user);
        $this->entityManager->flush();

        $foundUser = $this->repository->getById($user->getId());

        $this->assertSame($user->getId(), $foundUser->getId());
        $this->assertSame('test@example.com', $foundUser->getEmail());
    }

    public function testGetByIdThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('User not found: 999999');

        $this->repository->getById(999999);
    }

    public function testFindByEmail(): void
    {
        $user = new User('find@example.com', 'finduser', 'password');
        $this->repository->save($user);
        $this->entityManager->flush();

        $foundUser = $this->repository->findByEmail('find@example.com');

        $this->assertNotNull($foundUser);
        $this->assertSame($user->getId(), $foundUser->getId());
        $this->assertSame('find@example.com', $foundUser->getEmail());
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($result);
    }

    public function testFindByUsername(): void
    {
        $user = new User('user@example.com', 'uniqueuser', 'password');
        $this->repository->save($user);
        $this->entityManager->flush();

        $foundUser = $this->repository->findByUsername('uniqueuser');

        $this->assertNotNull($foundUser);
        $this->assertSame($user->getId(), $foundUser->getId());
        $this->assertSame('uniqueuser', $foundUser->getUsername());
    }

    public function testFindByUsernameReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByUsername('nonexistentuser');

        $this->assertNull($result);
    }

    public function testDelete(): void
    {
        $user = new User('delete@example.com', 'deleteuser', 'password');
        $this->repository->save($user);
        $this->entityManager->flush();
        $userId = $user->getId();

        $this->repository->delete($user);
        $this->entityManager->flush();

        $result = $this->repository->findById($userId);
        $this->assertNull($result);
    }

    public function testGetByIdWithLock(): void
    {
        $user = new User('lock@example.com', 'lockuser', 'password');
        $this->repository->save($user);
        $this->entityManager->flush();

        $foundUser = $this->repository->getById($user->getId(), true);

        $this->assertSame($user->getId(), $foundUser->getId());
    }
}
