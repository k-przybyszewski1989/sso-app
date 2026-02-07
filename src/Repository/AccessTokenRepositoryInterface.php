<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\Exception\EntityNotFoundException;

interface AccessTokenRepositoryInterface
{
    /**
     * Find access token by token string.
     */
    public function findByToken(string $token): ?AccessToken;

    /**
     * Get access token by token string or throw exception.
     *
     * @param bool $lock Use pessimistic locking
     * @throws EntityNotFoundException
     */
    public function getByToken(string $token, bool $lock = false): AccessToken;

    /**
     * Find all access tokens for a user.
     *
     * @return array<AccessToken>
     */
    public function findByUser(User $user): array;

    /**
     * Find all access tokens for a client.
     *
     * @return array<AccessToken>
     */
    public function findByClient(OAuth2Client $client): array;

    /**
     * Save access token entity.
     */
    public function save(AccessToken $token): void;

    /**
     * Delete access token entity.
     */
    public function delete(AccessToken $token): void;

    /**
     * Delete all expired access tokens.
     *
     * @return int Number of deleted tokens
     */
    public function deleteExpired(): int;

    /**
     * Revoke all access tokens for a user.
     *
     * @return int Number of revoked tokens
     */
    public function revokeAllForUser(User $user): int;

    /**
     * Revoke all access tokens for a client.
     *
     * @return int Number of revoked tokens
     */
    public function revokeAllForClient(OAuth2Client $client): int;
}
