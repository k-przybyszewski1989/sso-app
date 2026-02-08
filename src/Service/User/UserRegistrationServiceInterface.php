<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use InvalidArgumentException;

interface UserRegistrationServiceInterface
{
    /**
     * Register a new user with the provided credentials.
     *
     * @param string $email User's email address
     * @param string $username User's username
     * @param string $password Plain text password (will be hashed)
     * @throws InvalidArgumentException If email or username is already taken
     * @return User The newly created user entity
     */
    public function registerUser(string $email, string $username, string $password): User;
}
