<?php

declare(strict_types=1);

namespace App\Request\OAuth2;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TokenRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['authorization_code', 'client_credentials', 'refresh_token'])]
        public string $grantType,

        public ?string $code = null,
        public ?string $redirectUri = null,
        public ?string $clientId = null,
        public ?string $clientSecret = null,
        public ?string $refreshToken = null,
        public ?string $scope = null,
        public ?string $codeVerifier = null,
    ) {
    }
}
