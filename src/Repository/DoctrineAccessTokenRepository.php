<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findByToken(string $token): ?AccessToken
    {
        return $this->entityManager
            ->getRepository(AccessToken::class)
            ->findOneBy(['token' => $token]);
    }

    public function getByToken(string $token, bool $lock = false): AccessToken
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(AccessToken::class, 't')
            ->where('t.token = :token')
            ->setParameter('token', $token)
            ->getQuery();

        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var AccessToken|null $accessToken */
        $accessToken = $query->getOneOrNullResult();

        if (null === $accessToken) {
            throw new EntityNotFoundException('AccessToken', $token);
        }

        return $accessToken;
    }

    /**
     * @return array<AccessToken>
     */
    public function findByUser(User $user): array
    {
        return $this->entityManager
            ->getRepository(AccessToken::class)
            ->findBy(['user' => $user]);
    }

    /**
     * @return array<AccessToken>
     */
    public function findByClient(OAuth2Client $client): array
    {
        return $this->entityManager
            ->getRepository(AccessToken::class)
            ->findBy(['client' => $client]);
    }

    public function save(AccessToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    public function delete(AccessToken $token): void
    {
        $this->entityManager->remove($token);
        $this->entityManager->flush();
    }

    public function deleteExpired(): int
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->delete(AccessToken::class, 't')
            ->where('t.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery();

        $result = $query->execute();
        assert(is_int($result));

        return $result;
    }

    public function revokeAllForUser(User $user): int
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->update(AccessToken::class, 't')
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

    public function revokeAllForClient(OAuth2Client $client): int
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->update(AccessToken::class, 't')
            ->set('t.revoked', ':revoked')
            ->set('t.revokedAt', ':revokedAt')
            ->where('t.client = :client')
            ->andWhere('t.revoked = :notRevoked')
            ->setParameter('revoked', true)
            ->setParameter('revokedAt', new DateTimeImmutable())
            ->setParameter('client', $client)
            ->setParameter('notRevoked', false)
            ->getQuery();

        $result = $query->execute();
        assert(is_int($result));

        return $result;
    }
}
