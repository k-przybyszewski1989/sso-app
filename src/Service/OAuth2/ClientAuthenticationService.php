<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\OAuth2Client;
use App\Exception\OAuth2\InvalidClientException;
use App\Repository\OAuth2ClientRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class ClientAuthenticationService implements ClientAuthenticationServiceInterface
{
    public function __construct(
        private OAuth2ClientRepositoryInterface $clientRepository,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    /** {@inheritDoc} */
    public function authenticate(?string $authHeader, ?string $clientId, ?string $clientSecret): OAuth2Client
    {
        // Try to extract credentials from Authorization header (Basic Auth)
        if (null !== $authHeader && str_starts_with($authHeader, 'Basic ')) {
            $credentials = $this->extractBasicAuthCredentials($authHeader);
            if (null !== $credentials) {
                [$clientId, $clientSecret] = $credentials;
            }
        }

        // Validate that we have both client_id and client_secret
        if (null === $clientId || null === $clientSecret) {
            throw new InvalidClientException('Client authentication failed: missing credentials');
        }

        // Find the client
        $client = $this->clientRepository->findByClientId($clientId);
        if (null === $client) {
            throw new InvalidClientException('Client authentication failed: invalid client');
        }

        // Check if client is active
        if (!$client->isActive()) {
            throw new InvalidClientException('Client authentication failed: client is inactive');
        }

        // Verify client secret for confidential clients
        if ($client->isConfidential()) {
            $hasher = $this->passwordHasherFactory->getPasswordHasher($client);
            if (!$hasher->verify($client->getClientSecretHash(), $clientSecret)) {
                throw new InvalidClientException('Client authentication failed: invalid credentials');
            }
        }

        return $client;
    }

    /**
     * @return array{string, string}|null
     */
    private function extractBasicAuthCredentials(string $authHeader): ?array
    {
        $encoded = substr($authHeader, 6); // Remove 'Basic ' prefix
        $decoded = base64_decode($encoded, true);

        if (false === $decoded) {
            return null;
        }

        $decoded = trim($decoded);

        $parts = explode(':', $decoded, 2);
        if (2 !== count($parts)) {
            return null;
        }

        return $parts;
    }
}
