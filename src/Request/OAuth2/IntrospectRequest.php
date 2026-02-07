<?php

declare(strict_types=1);

namespace App\Request\OAuth2;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class IntrospectRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
