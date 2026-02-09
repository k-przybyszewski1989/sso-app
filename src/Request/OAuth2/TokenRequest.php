<?php

declare(strict_types=1);

namespace App\Request\OAuth2;

use App\Enum\GrantType;

final readonly class TokenRequest
{
    public function __construct(
        public GrantType $grantType,
        public ?string $code = null,
        public ?string $redirectUri = null,
        public ?string $clientId = null,
        public ?string $clientSecret = null,
        public ?string $refreshToken = null,
        public ?string $scope = null,
        public ?string $codeVerifier = null,
        public ?string $authorizationHeader = null,
    ) {
    }
}
