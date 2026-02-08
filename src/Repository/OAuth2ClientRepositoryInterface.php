<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OAuth2Client;
use App\Exception\EntityNotFoundException;

interface OAuth2ClientRepositoryInterface
{
    /**
     * Find client by ID.
     */
    public function findById(int $id): ?OAuth2Client;

    /**
     * Get client by ID or throw exception.
     *
     * @param bool $lock Use pessimistic locking
     * @throws EntityNotFoundException
     */
    public function getById(int $id, bool $lock = false): OAuth2Client;

    /**
     * Find client by client_id.
     */
    public function findByClientId(string $clientId): ?OAuth2Client;

    /**
     * Get client by client_id or throw exception.
     *
     * @param bool $lock Use pessimistic locking
     * @throws EntityNotFoundException
     */
    public function getByClientId(string $clientId, bool $lock = false): OAuth2Client;

    /**
     * Find all clients.
     *
     * @return array<OAuth2Client>
     */
    public function findAll(): array;

    /**
     * Find only active clients.
     *
     * @return array<OAuth2Client>
     */
    public function findActive(): array;

    /**
     * Save client entity.
     */
    public function save(OAuth2Client $client): void;

    /**
     * Delete client entity.
     */
    public function delete(OAuth2Client $client): void;
}
