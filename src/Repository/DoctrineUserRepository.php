<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Exception\EntityNotFoundException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    /**
     * {@inheritDoc}
     */
    public function getById(int $id, bool $lock = false): User
    {
        $user = $this->entityManager->find(
            User::class,
            $id,
            $lock ? LockMode::PESSIMISTIC_WRITE : null,
        );

        if (null === $user) {
            throw new EntityNotFoundException('User', (string) $id);
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function findByEmail(string $email): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUsername(string $username): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['username' => $username]);
    }

    /**
     * {@inheritDoc}
     */
    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
