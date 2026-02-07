<?php

declare(strict_types=1);

namespace App\Request\OAuth2;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AuthorizationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['code'])]
        public string $responseType,

        #[Assert\NotBlank]
        public string $clientId,

        #[Assert\NotBlank]
        #[Assert\Url]
        public string $redirectUri,

        public ?string $scope = null,
        public ?string $state = null,
        public ?string $codeChallenge = null,

        #[Assert\Choice(choices: ['plain', 'S256'], allowNull: true)]
        public ?string $codeChallengeMethod = null,
    ) {
    }
}
