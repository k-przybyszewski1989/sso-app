<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserRegistrationService implements UserRegistrationServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function registerUser(string $email, string $username, string $password): User
    {
        // Validate email uniqueness
        if (null !== $this->userRepository->findByEmail($email)) {
            $this->logger->warning('User registration failed: email already exists', [
                'email' => $email,
            ]);

            throw new InvalidArgumentException('Email is already taken');
        }

        // Validate username uniqueness
        if (null !== $this->userRepository->findByUsername($username)) {
            $this->logger->warning('User registration failed: username already exists', [
                'username' => $username,
            ]);

            throw new InvalidArgumentException('Username is already taken');
        }

        // Create user entity with plain password (will be hashed)
        $user = new User($email, $username, $password);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Persist user
        $this->userRepository->save($user);

        $this->logger->info('User registered successfully', [
            'userId' => $user->getId(),
            'email' => $email,
            'username' => $username,
        ]);

        return $user;
    }
}
