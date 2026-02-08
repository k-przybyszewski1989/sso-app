<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepositoryInterface;
use App\Service\User\UserRegistrationServiceInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'user:create-admin',
    description: 'Create an admin user with ROLE_ADMIN',
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly UserRegistrationServiceInterface $userRegistrationService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin user email')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Admin username')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Create Admin User');

        // Get email (from option or ask)
        $emailOption = $input->getOption('email');
        $email = is_string($emailOption) ? $emailOption : null;

        if (null === $email) {
            $emailInput = $io->ask('Email address', null, function (mixed $value): string {
                if (empty($value) || !is_string($value)) {
                    throw new InvalidArgumentException('Email cannot be empty');
                }

                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Invalid email address');
                }

                return $value;
            });
            assert(is_string($emailInput));
            $email = $emailInput;
        }

        // Get username (from option or ask)
        $usernameOption = $input->getOption('username');
        $username = is_string($usernameOption) ? $usernameOption : null;

        if (null === $username) {
            $usernameInput = $io->ask('Username', null, function (mixed $value): string {
                if (empty($value) || !is_string($value)) {
                    throw new InvalidArgumentException('Username cannot be empty');
                }

                if (strlen($value) < 3) {
                    throw new InvalidArgumentException('Username must be at least 3 characters');
                }

                if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                    throw new InvalidArgumentException('Username can only contain letters, numbers, and underscores');
                }

                return $value;
            });
            assert(is_string($usernameInput));
            $username = $usernameInput;
        }

        // Get password (from option or ask)
        $passwordOption = $input->getOption('password');
        $password = is_string($passwordOption) ? $passwordOption : null;

        if (null === $password) {
            $passwordInput = $io->askHidden('Password (min 8 characters)', function (mixed $value): string {
                if (empty($value) || !is_string($value)) {
                    throw new InvalidArgumentException('Password cannot be empty');
                }

                if (strlen($value) < 8) {
                    throw new InvalidArgumentException('Password must be at least 8 characters');
                }

                return $value;
            });
            assert(is_string($passwordInput));
            $password = $passwordInput;

            // Confirm password
            $confirmPassword = $io->askHidden('Confirm password');

            if ($password !== $confirmPassword) {
                $io->error('Passwords do not match');

                return Command::FAILURE;
            }
        }

        try {
            // Create user
            $user = $this->userRegistrationService->registerUser($email, $username, $password);

            // Add ROLE_ADMIN
            $user->setRoles(['ROLE_ADMIN']);
            $this->userRepository->save($user);

            $io->success([
                'Admin user created successfully!',
                sprintf('Email: %s', $email),
                sprintf('Username: %s', $username),
                sprintf('User ID: %d', $user->getId()),
            ]);

            return Command::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
