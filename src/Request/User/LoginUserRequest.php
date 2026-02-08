<?php

declare(strict_types=1);

namespace App\Request\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginUserRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        public string $password,
    ) {
    }
}
