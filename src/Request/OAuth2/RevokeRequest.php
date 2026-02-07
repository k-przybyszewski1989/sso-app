<?php

declare(strict_types=1);

namespace App\Request\OAuth2;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RevokeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $token,

        #[Assert\Choice(choices: ['access_token', 'refresh_token'], allowNull: true)]
        public ?string $tokenTypeHint = null,
    ) {
    }
}
