<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthorizationCode;
use App\Exception\EntityNotFoundException;

interface AuthorizationCodeRepositoryInterface
{
    /**
     * Find authorization code by code string.
     */
    public function findByCode(string $code): ?AuthorizationCode;

    /**
     * Get authorization code by code string or throw exception.
     *
     * @param bool $lock Use pessimistic locking
     * @throws EntityNotFoundException
     */
    public function getByCode(string $code, bool $lock = false): AuthorizationCode;

    /**
     * Save authorization code entity.
     */
    public function save(AuthorizationCode $code): void;

    /**
     * Delete authorization code entity.
     */
    public function delete(AuthorizationCode $code): void;

    /**
     * Delete all expired authorization codes.
     *
     * @return int Number of deleted codes
     */
    public function deleteExpired(): int;
}
