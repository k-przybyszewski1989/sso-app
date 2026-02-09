<?php

declare(strict_types=1);

namespace App\Request\OAuth2;

use App\Enum\GrantType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateClientRequest
{
    /**
     * @param array<string> $redirectUris
     * @param array<GrantType> $grantTypes
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
        public array $grantTypes,
        public bool $confidential = true,
        public ?string $description = null,
    ) {
    }
}
