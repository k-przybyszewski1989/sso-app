<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;
use App\Service\User\UserRegistrationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserRegistrationServiceTest extends TestCase
{
    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    public function testRegisterUserSuccessfully(): void
    {
        $email = 'test@example.com';
        $username = 'testuser';
        $password = 'password123';
        $hashedPassword = 'hashed_password';

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);
        $userRepository->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn(null);
        $userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user) {
                $this->setEntityId($user, 1);

                return true;
            }));

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn($hashedPassword);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info');

        $service = new UserRegistrationService($userRepository, $passwordHasher, $logger);
        $user = $service->registerUser($email, $username, $password);

        $this->assertSame($email, $user->getEmail());
        $this->assertSame($username, $user->getUsername());
    }

    public function testRegisterUserThrowsExceptionWhenEmailExists(): void
    {
        $email = 'existing@example.com';
        $existingUser = new User($email, 'existing', 'password');
        $this->setEntityId($existingUser, 1);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($existingUser);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning');

        $service = new UserRegistrationService($userRepository, $passwordHasher, $logger);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email is already taken');

        $service->registerUser($email, 'newuser', 'password123');
    }

    public function testRegisterUserThrowsExceptionWhenUsernameExists(): void
    {
        $username = 'existinguser';
        $existingUser = new User('existing@example.com', $username, 'password');
        $this->setEntityId($existingUser, 1);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);
        $userRepository->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn($existingUser);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning');

        $service = new UserRegistrationService($userRepository, $passwordHasher, $logger);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username is already taken');

        $service->registerUser('new@example.com', $username, 'password123');
    }
}
