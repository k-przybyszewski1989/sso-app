<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthorizationCode;
use App\Exception\EntityNotFoundException;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAuthorizationCodeRepository implements AuthorizationCodeRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findByCode(string $code): ?AuthorizationCode
    {
        return $this->entityManager
            ->getRepository(AuthorizationCode::class)
            ->findOneBy(['code' => $code]);
    }

    public function getByCode(string $code, bool $lock = false): AuthorizationCode
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(AuthorizationCode::class, 'c')
            ->where('c.code = :code')
            ->setParameter('code', $code)
            ->getQuery();

        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var AuthorizationCode|null $authorizationCode */
        $authorizationCode = $query->getOneOrNullResult();

        if (null === $authorizationCode) {
            throw new EntityNotFoundException('AuthorizationCode', $code);
        }

        return $authorizationCode;
    }

    public function save(AuthorizationCode $code): void
    {
        $this->entityManager->persist($code);
        $this->entityManager->flush();
    }

    public function delete(AuthorizationCode $code): void
    {
        $this->entityManager->remove($code);
        $this->entityManager->flush();
    }

    public function deleteExpired(): int
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->delete(AuthorizationCode::class, 'c')
            ->where('c.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery();

        $result = $query->execute();
        assert(is_int($result));

        return $result;
    }
}
