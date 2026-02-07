<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findByToken(string $token): ?RefreshToken
    {
        return $this->entityManager
            ->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);
    }

    /**
     * {@inheritDoc}
     */
    public function getByToken(string $token, bool $lock = false): RefreshToken
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(RefreshToken::class, 't')
            ->where('t.token = :token')
            ->setParameter('token', $token)
            ->getQuery();

        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var RefreshToken|null $refreshToken */
        $refreshToken = $query->getOneOrNullResult();

        if (null === $refreshToken) {
            throw new EntityNotFoundException('RefreshToken', $token);
        }

        return $refreshToken;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<RefreshToken>
     */
    public function findByUser(User $user): array
    {
        return $this->entityManager
            ->getRepository(RefreshToken::class)
            ->findBy(['user' => $user]);
    }

    /**
     * {@inheritDoc}
     */
    public function save(RefreshToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(RefreshToken $token): void
    {
        $this->entityManager->remove($token);
        $this->entityManager->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteExpired(): int
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->delete(RefreshToken::class, 't')
            ->where('t.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery();

        $result = $query->execute();
        assert(is_int($result));

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function revokeAllForUser(User $user): int
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->update(RefreshToken::class, 't')
            ->set('t.revoked', ':revoked')
            ->set('t.revokedAt', ':revokedAt')
            ->where('t.user = :user')
            ->andWhere('t.revoked = :notRevoked')
            ->setParameter('revoked', true)
            ->setParameter('revokedAt', new DateTimeImmutable())
            ->setParameter('user', $user)
            ->setParameter('notRevoked', false)
            ->getQuery();

        $result = $query->execute();
        assert(is_int($result));

        return $result;
    }
}
