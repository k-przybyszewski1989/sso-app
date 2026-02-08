<?php

declare(strict_types=1);

namespace App\Request\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterUserRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 50)]
        #[Assert\Regex(pattern: '/^[a-zA-Z0-9_]+$/', message: 'Username can only contain letters, numbers, and underscores')]
        public string $username,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public string $password,
    ) {
    }
}
