<?php

declare(strict_types=1);

namespace App\Request\OAuth2;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateClientRequest
{
    /**
     * @param array<string> $redirectUris
     * @param array<string> $grantTypes
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\NotBlank(),
            new Assert\Url(),
        ])]
        public array $redirectUris,

        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\NotBlank(),
            new Assert\Choice(choices: ['authorization_code', 'client_credentials', 'refresh_token']),
        ])]
        public array $grantTypes,

        public bool $confidential = true,
        public ?string $description = null,
    ) {
    }
}
