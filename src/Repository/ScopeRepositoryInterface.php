<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Scope;
use App\Exception\EntityNotFoundException;

interface ScopeRepositoryInterface
{
    /**
     * Find scope by ID.
     */
    public function findById(int $id): ?Scope;

    /**
     * Get scope by ID or throw exception.
     *
     * @param bool $lock Use pessimistic locking
     * @throws EntityNotFoundException
     */
    public function getById(int $id, bool $lock = false): Scope;

    /**
     * Find scope by identifier.
     */
    public function findByIdentifier(string $identifier): ?Scope;

    /**
     * Get scope by identifier or throw exception.
     *
     * @param bool $lock Use pessimistic locking
     * @throws EntityNotFoundException
     */
    public function getByIdentifier(string $identifier, bool $lock = false): Scope;

    /**
     * Find all scopes.
     *
     * @return array<Scope>
     */
    public function findAll(): array;

    /**
     * Find only default scopes.
     *
     * @return array<Scope>
     */
    public function findDefaults(): array;

    /**
     * Find scopes by array of identifiers.
     *
     * @param array<string> $identifiers
     * @return array<Scope>
     */
    public function findByIdentifiers(array $identifiers): array;

    /**
     * Save scope entity.
     */
    public function save(Scope $scope): void;

    /**
     * Delete scope entity.
     */
    public function delete(Scope $scope): void;
}
