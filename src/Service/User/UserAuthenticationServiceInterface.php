<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Exception\InvalidCredentialsException;

interface UserAuthenticationServiceInterface
{
    /**
     * Authenticate a user with email and password.
     *
     * @param string $email User's email address
     * @param string $password Plain text password to verify
     * @throws InvalidCredentialsException If credentials are invalid
     * @return User The authenticated user entity
     */
    public function authenticate(string $email, string $password): User;
}
