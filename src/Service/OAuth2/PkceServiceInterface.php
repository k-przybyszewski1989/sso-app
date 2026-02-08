<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

interface PkceServiceInterface
{
    /**
     * Validates PKCE code verifier against the stored code challenge.
     *
     * @return bool True if validation succeeds, false otherwise
     */
    public function validate(string $codeVerifier, string $codeChallenge, string $method): bool;
}
