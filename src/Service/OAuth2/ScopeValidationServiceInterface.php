<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

interface ScopeValidationServiceInterface
{
    /**
     * @param array<string> $requestedScopes
     * @param array<string> $allowedScopes
     * @return array<string>
     */
    public function validate(array $requestedScopes, array $allowedScopes): array;
}
