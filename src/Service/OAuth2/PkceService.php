<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Exception\OAuth2\InvalidRequestException;

final readonly class PkceService implements PkceServiceInterface
{
    /** {@inheritDoc} */
    public function validate(string $codeVerifier, string $codeChallenge, string $method): bool
    {
        if ('plain' === $method) {
            return hash_equals($codeChallenge, $codeVerifier);
        }

        if ('S256' === $method) {
            $hash = hash('sha256', $codeVerifier, true);
            $computed = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

            return hash_equals($codeChallenge, $computed);
        }

        throw new InvalidRequestException(
            sprintf('Unsupported code challenge method: %s', $method)
        );
    }
}
