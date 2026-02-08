<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\EntityNotFoundException;

interface RefreshTokenRepositoryInterface
{
    /**
     * Find refresh token by token string.
     */
    public function findByToken(string $token): ?RefreshToken;

    /**
     * Get refresh token by token string or throw exception.
     *
     * @param bool $lock Use pessimistic locking
     * @throws EntityNotFoundException
     */
    public function getByToken(string $token, bool $lock = false): RefreshToken;

    /**
     * Find all refresh tokens for a user.
     *
     * @return array<RefreshToken>
     */
    public function findByUser(User $user): array;

    /**
     * Save refresh token entity.
     */
    public function save(RefreshToken $token): void;

    /**
     * Delete refresh token entity.
     */
    public function delete(RefreshToken $token): void;

    /**
     * Delete all expired refresh tokens.
     *
     * @return int Number of deleted tokens
     */
    public function deleteExpired(): int;

    /**
     * Revoke all refresh tokens for a user.
     *
     * @return int Number of revoked tokens
     */
    public function revokeAllForUser(User $user): int;
}
