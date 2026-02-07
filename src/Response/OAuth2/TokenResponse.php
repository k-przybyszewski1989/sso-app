<?php

declare(strict_types=1);

namespace App\Response\OAuth2;

final readonly class TokenResponse
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public ?string $refreshToken = null,
        public ?string $scope = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
        ];

        if (null !== $this->refreshToken) {
            $data['refresh_token'] = $this->refreshToken;
        }

        if (null !== $this->scope) {
            $data['scope'] = $this->scope;
        }

        return $data;
    }
}
