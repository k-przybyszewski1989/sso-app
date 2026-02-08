<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateAdminUserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepositoryInterface $userRepository;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->userRepository = $container->get(UserRepositoryInterface::class);

        $this->entityManager->beginTransaction();

        $kernel = self::$kernel;
        assert(null !== $kernel);
        $application = new Application($kernel);
        $command = $application->find('user:create-admin');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        $this->entityManager->clear();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testCommandCreatesAdminUserWithProvidedCredentials(): void
    {
        $this->commandTester->execute([
            '--email' => 'admin@example.com',
            '--username' => 'adminuser',
            '--password' => 'SecurePassword123',
        ]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Admin user created successfully!', $output);
        $this->assertStringContainsString('Email: admin@example.com', $output);
        $this->assertStringContainsString('Username: adminuser', $output);

        $user = $this->userRepository->findByEmail('admin@example.com');
        $this->assertNotNull($user);
        $this->assertSame('adminuser', $user->getUsername());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testCommandPromptsForMissingCredentials(): void
    {
        $this->commandTester->setInputs([
            'prompted@example.com',
            'prompteduser',
            'PromptedPassword123',
            'PromptedPassword123',
        ]);

        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Email address', $output);
        $this->assertStringContainsString('Username', $output);
        $this->assertStringContainsString('Password (min 8 characters)', $output);
        $this->assertStringContainsString('Confirm password', $output);
        $this->assertStringContainsString('Admin user created successfully!', $output);

        $user = $this->userRepository->findByEmail('prompted@example.com');
        $this->assertNotNull($user);
        $this->assertSame('prompteduser', $user->getUsername());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testCommandFailsWhenEmailAlreadyExists(): void
    {
        $existingUser = new User('existing@example.com', 'existinguser', 'password');
        $this->userRepository->save($existingUser);
        $this->entityManager->flush();

        $this->commandTester->execute([
            '--email' => 'existing@example.com',
            '--username' => 'newusername',
            '--password' => 'SecurePassword123',
        ]);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('already taken', $output);
    }

    public function testCommandFailsWhenUsernameAlreadyExists(): void
    {
        $existingUser = new User('user@example.com', 'existingname', 'password');
        $this->userRepository->save($existingUser);
        $this->entityManager->flush();

        $this->commandTester->execute([
            '--email' => 'newemail@example.com',
            '--username' => 'existingname',
            '--password' => 'SecurePassword123',
        ]);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('already taken', $output);
    }

    public function testCommandValidatesEmailFormat(): void
    {
        $this->commandTester->setInputs([
            'invalid-email',
            'valid@example.com',
            'validuser',
            'ValidPassword123',
            'ValidPassword123',
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Invalid email address', $output);
        $this->assertStringContainsString('Admin user created successfully!', $output);

        $user = $this->userRepository->findByEmail('valid@example.com');
        $this->assertNotNull($user);
    }

    public function testCommandValidatesEmptyEmail(): void
    {
        $this->commandTester->setInputs([
            '',
            'valid@example.com',
            'validuser',
            'ValidPassword123',
            'ValidPassword123',
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Email cannot be empty', $output);
        $this->assertStringContainsString('Admin user created successfully!', $output);
    }

    public function testCommandValidatesUsernameMinimumLength(): void
    {
        $this->commandTester->setInputs([
            'user@example.com',
            'ab',
            'validuser',
            'ValidPassword123',
            'ValidPassword123',
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Username must be at least 3 characters', $output);
        $this->assertStringContainsString('Admin user created successfully!', $output);
    }

    public function testCommandValidatesUsernameFormat(): void
    {
        $this->commandTester->setInputs([
            'user@example.com',
            'invalid-username!',
            'validuser',
            'ValidPassword123',
            'ValidPassword123',
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Username can only contain letters, numbers, and underscores', $output);
        $this->assertStringContainsString('Admin user created successfully!', $output);
    }

    public function testCommandValidatesEmptyUsername(): void
    {
        $this->commandTester->setInputs([
            'user@example.com',
            '',
            'validuser',
            'ValidPassword123',
            'ValidPassword123',
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Username cannot be empty', $output);
        $this->assertStringContainsString('Admin user created successfully!', $output);
    }

    public function testCommandRequiresPasswordMinimumLength(): void
    {
        $this->commandTester->setInputs([
            'user@example.com',
            'validuser',
            'short',
            'ValidPassword123',
            'ValidPassword123',
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Password must be at least 8 characters', $output);
        $this->assertStringContainsString('Admin user created successfully!', $output);
    }

    public function testCommandValidatesEmptyPassword(): void
    {
        $this->commandTester->setInputs([
            'user@example.com',
            'validuser',
            '',
            'ValidPassword123',
            'ValidPassword123',
        ]);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Password cannot be empty', $output);
        $this->assertStringContainsString('Admin user created successfully!', $output);
    }

    public function testCommandFailsWhenPasswordsDoNotMatch(): void
    {
        $this->commandTester->setInputs([
            'user@example.com',
            'validuser',
            'ValidPassword123',
            'DifferentPassword456',
        ]);

        $this->commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Passwords do not match', $output);

        $user = $this->userRepository->findByEmail('user@example.com');
        $this->assertNull($user);
    }

    public function testCommandSetsRoleAdmin(): void
    {
        $this->commandTester->execute([
            '--email' => 'roletest@example.com',
            '--username' => 'roletestuser',
            '--password' => 'SecurePassword123',
        ]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $user = $this->userRepository->findByEmail('roletest@example.com');
        $this->assertNotNull($user);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertCount(2, $roles);
    }

    public function testCommandDisplaysUserIdInOutput(): void
    {
        $this->commandTester->execute([
            '--email' => 'userid@example.com',
            '--username' => 'useridtest',
            '--password' => 'SecurePassword123',
        ]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('User ID:', $output);

        $user = $this->userRepository->findByEmail('userid@example.com');
        $this->assertNotNull($user);
        $this->assertStringContainsString((string) $user->getId(), $output);
    }
}
