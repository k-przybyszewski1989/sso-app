<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Scope;
use App\Exception\EntityNotFoundException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineScopeRepository implements ScopeRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Scope
    {
        return $this->entityManager->find(Scope::class, $id);
    }

    /**
     * {@inheritDoc}
     */
    public function getById(int $id, bool $lock = false): Scope
    {
        $scope = $this->entityManager->find(
            Scope::class,
            $id,
            $lock ? LockMode::PESSIMISTIC_WRITE : null,
        );

        if (null === $scope) {
            throw new EntityNotFoundException('Scope', (string) $id);
        }

        return $scope;
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdentifier(string $identifier): ?Scope
    {
        return $this->entityManager
            ->getRepository(Scope::class)
            ->findOneBy(['identifier' => $identifier]);
    }

    /**
     * {@inheritDoc}
     */
    public function getByIdentifier(string $identifier, bool $lock = false): Scope
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(Scope::class, 's')
            ->where('s.identifier = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery();

        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var Scope|null $scope */
        $scope = $query->getOneOrNullResult();

        if (null === $scope) {
            throw new EntityNotFoundException('Scope', $identifier);
        }

        return $scope;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<Scope>
     */
    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(Scope::class)
            ->findAll();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<Scope>
     */
    public function findDefaults(): array
    {
        return $this->entityManager
            ->getRepository(Scope::class)
            ->findBy(['isDefault' => true]);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string> $identifiers
     * @return array<Scope>
     */
    public function findByIdentifiers(array $identifiers): array
    {
        if (empty($identifiers)) {
            return [];
        }

        /** @var array<Scope> $result */
        $result = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(Scope::class, 's')
            ->where('s.identifier IN (:identifiers)')
            ->setParameter('identifiers', $identifiers)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function save(Scope $scope): void
    {
        $this->entityManager->persist($scope);
        $this->entityManager->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Scope $scope): void
    {
        $this->entityManager->remove($scope);
        $this->entityManager->flush();
    }
}
