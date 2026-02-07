<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Exception\EntityNotFoundException;

interface UserRepositoryInterface
{
    /**
     * Find user by ID.
     */
    public function findById(int $id): ?User;

    /**
     * Get user by ID or throw exception.
     *
     * @param bool $lock Use pessimistic locking
     * @throws EntityNotFoundException
     */
    public function getById(int $id, bool $lock = false): User;

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find user by username.
     */
    public function findByUsername(string $username): ?User;

    /**
     * Save user entity.
     */
    public function save(User $user): void;

    /**
     * Delete user entity.
     */
    public function delete(User $user): void;
}
