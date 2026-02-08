<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User;
use App\Exception\InvalidCredentialsException;
use App\Repository\UserRepositoryInterface;
use App\Service\User\UserAuthenticationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserAuthenticationServiceTest extends TestCase
{
    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    public function testAuthenticateSuccessfully(): void
    {
        $email = 'test@example.com';
        $password = 'password123';
        $user = new User($email, 'testuser', 'hashed_password');
        $this->setEntityId($user, 1);
        $user->setEnabled(true);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);
        $userRepository->expects($this->once())
            ->method('save')
            ->with($user);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $password)
            ->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info');

        $service = new UserAuthenticationService($userRepository, $passwordHasher, $logger);
        $authenticatedUser = $service->authenticate($email, $password);

        $this->assertSame($user, $authenticatedUser);
        $this->assertNotNull($authenticatedUser->getLastLoginAt());
    }

    public function testAuthenticateThrowsExceptionWhenUserNotFound(): void
    {
        $email = 'notfound@example.com';
        $password = 'password123';

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning');

        $service = new UserAuthenticationService($userRepository, $passwordHasher, $logger);

        $this->expectException(InvalidCredentialsException::class);

        $service->authenticate($email, $password);
    }

    public function testAuthenticateThrowsExceptionWhenUserDisabled(): void
    {
        $email = 'test@example.com';
        $password = 'password123';
        $user = new User($email, 'testuser', 'hashed_password');
        $this->setEntityId($user, 1);
        $user->setEnabled(false);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning');

        $service = new UserAuthenticationService($userRepository, $passwordHasher, $logger);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('User account is disabled');

        $service->authenticate($email, $password);
    }

    public function testAuthenticateThrowsExceptionWhenPasswordInvalid(): void
    {
        $email = 'test@example.com';
        $password = 'wrong_password';
        $user = new User($email, 'testuser', 'hashed_password');
        $this->setEntityId($user, 1);
        $user->setEnabled(true);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $password)
            ->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning');

        $service = new UserAuthenticationService($userRepository, $passwordHasher, $logger);

        $this->expectException(InvalidCredentialsException::class);

        $service->authenticate($email, $password);
    }
}
