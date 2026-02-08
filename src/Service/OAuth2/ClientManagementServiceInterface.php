<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\OAuth2Client;

interface ClientManagementServiceInterface
{
    /**
     * Creates a new OAuth2 client.
     *
     * @param string $name Client application name
     * @param array<string> $redirectUris Allowed redirect URIs
     * @param array<string> $grantTypes Allowed grant types
     * @param bool $confidential Whether client is confidential (requires secret)
     * @return array{client_id: string, client_secret: string, name: string} Client credentials
     */
    public function createClient(string $name, array $redirectUris, array $grantTypes, bool $confidential): array;

    /**
     * Lists all OAuth2 clients.
     *
     * @return array<OAuth2Client>
     */
    public function listClients(): array;

    /**
     * Deletes an OAuth2 client by client ID.
     *
     * @param string $clientId The client ID to delete
     */
    public function deleteClient(string $clientId): void;
}
