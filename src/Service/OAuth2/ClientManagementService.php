<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\OAuth2Client;
use App\Enum\GrantType;
use App\Exception\EntityNotFoundException;
use App\Repository\OAuth2ClientRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class ClientManagementService implements ClientManagementServiceInterface
{
    public function __construct(
        private OAuth2ClientRepositoryInterface $clientRepository,
        private TokenGeneratorServiceInterface $tokenGenerator,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function createClient(string $name, array $redirectUris, array $grantTypes, bool $confidential): array
    {
        // Generate unique client ID and secret
        $clientId = $this->tokenGenerator->generateClientId();
        $clientSecret = $this->tokenGenerator->generateClientSecret();

        // Create client entity with temporary secret (will be hashed)
        $client = new OAuth2Client($clientId, $clientSecret, $name);

        // Hash the client secret using bcrypt
        $hashedSecret = $this->passwordHasher->hashPassword($client, $clientSecret);
        $client->setClientSecretHash($hashedSecret);

        // Set client properties
        $client->setRedirectUris($redirectUris);
        $client->setGrantTypes(GrantType::toStringArray($grantTypes));
        $client->setConfidential($confidential);

        // Persist client
        $this->clientRepository->save($client);

        $this->logger->info('OAuth2 client created successfully', [
            'clientId' => $clientId,
            'name' => $name,
            'confidential' => $confidential,
            'grantTypes' => GrantType::toStringArray($grantTypes),
        ]);

        // Return client credentials (client_secret is only shown once)
        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'name' => $name,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function listClients(): array
    {
        return $this->clientRepository->findAll();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteClient(string $clientId): void
    {
        try {
            $client = $this->clientRepository->getByClientId($clientId);
        } catch (EntityNotFoundException $e) {
            $this->logger->warning('Attempted to delete non-existent OAuth2 client', [
                'clientId' => $clientId,
            ]);

            throw $e;
        }

        $this->clientRepository->delete($client);

        $this->logger->info('OAuth2 client deleted successfully', [
            'clientId' => $clientId,
            'name' => $client->getName(),
        ]);
    }
}
