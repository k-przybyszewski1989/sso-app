<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OAuth2Client;
use App\Exception\EntityNotFoundException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineOAuth2ClientRepository implements OAuth2ClientRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?OAuth2Client
    {
        return $this->entityManager->find(OAuth2Client::class, $id);
    }

    /**
     * {@inheritDoc}
     */
    public function getById(int $id, bool $lock = false): OAuth2Client
    {
        $client = $this->entityManager->find(
            OAuth2Client::class,
            $id,
            $lock ? LockMode::PESSIMISTIC_WRITE : null,
        );

        if (null === $client) {
            throw new EntityNotFoundException('OAuth2Client', (string) $id);
        }

        return $client;
    }

    /**
     * {@inheritDoc}
     */
    public function findByClientId(string $clientId): ?OAuth2Client
    {
        return $this->entityManager
            ->getRepository(OAuth2Client::class)
            ->findOneBy(['clientId' => $clientId]);
    }

    /**
     * {@inheritDoc}
     */
    public function getByClientId(string $clientId, bool $lock = false): OAuth2Client
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(OAuth2Client::class, 'c')
            ->where('c.clientId = :clientId')
            ->setParameter('clientId', $clientId)
            ->getQuery();

        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var OAuth2Client|null $client */
        $client = $query->getOneOrNullResult();

        if (null === $client) {
            throw new EntityNotFoundException('OAuth2Client', $clientId);
        }

        return $client;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<OAuth2Client>
     */
    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(OAuth2Client::class)
            ->findAll();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<OAuth2Client>
     */
    public function findActive(): array
    {
        return $this->entityManager
            ->getRepository(OAuth2Client::class)
            ->findBy(['active' => true]);
    }

    /**
     * {@inheritDoc}
     */
    public function save(OAuth2Client $client): void
    {
        $this->entityManager->persist($client);
        $this->entityManager->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(OAuth2Client $client): void
    {
        $this->entityManager->remove($client);
        $this->entityManager->flush();
    }
}
