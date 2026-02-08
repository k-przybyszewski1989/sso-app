<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Exception\InvalidCredentialsException;
use App\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserAuthenticationService implements UserAuthenticationServiceInterface
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
    public function authenticate(string $email, string $password): User
    {
        // Find user by email
        $user = $this->userRepository->findByEmail($email);

        if (null === $user) {
            $this->logger->warning('Authentication failed: user not found', [
                'email' => $email,
            ]);

            throw new InvalidCredentialsException();
        }

        // Check if user is enabled
        if (!$user->isEnabled()) {
            $this->logger->warning('Authentication failed: user account is disabled', [
                'userId' => $user->getId(),
                'email' => $email,
            ]);

            throw new InvalidCredentialsException('User account is disabled');
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->logger->warning('Authentication failed: invalid password', [
                'userId' => $user->getId(),
                'email' => $email,
            ]);

            throw new InvalidCredentialsException();
        }

        // Update last login timestamp
        $user->setLastLoginAt(new DateTimeImmutable());
        $this->userRepository->save($user);

        $this->logger->info('User authenticated successfully', [
            'userId' => $user->getId(),
            'email' => $email,
        ]);

        return $user;
    }
}
