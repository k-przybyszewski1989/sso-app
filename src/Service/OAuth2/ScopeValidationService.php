<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Exception\OAuth2\InvalidScopeException;
use App\Repository\ScopeRepositoryInterface;

final readonly class ScopeValidationService implements ScopeValidationServiceInterface
{
    public function __construct(
        private ScopeRepositoryInterface $scopeRepository,
    ) {
    }

    /** {@inheritDoc} */
    public function validate(array $requestedScopes, array $allowedScopes): array
    {
        if (empty($requestedScopes)) {
            return [];
        }

        // Verify all requested scopes exist in the system
        $existingScopes = $this->scopeRepository->findByIdentifiers($requestedScopes);
        $existingScopeIdentifiers = array_map(
            static fn ($scope) => $scope->getIdentifier(),
            $existingScopes
        );

        $invalidScopes = array_diff($requestedScopes, $existingScopeIdentifiers);
        if (!empty($invalidScopes)) {
            throw new InvalidScopeException(
                sprintf('Invalid scopes requested: %s', implode(', ', $invalidScopes))
            );
        }

        // Verify all requested scopes are allowed for the client
        $disallowedScopes = array_diff($requestedScopes, $allowedScopes);
        if (!empty($disallowedScopes)) {
            throw new InvalidScopeException(
                sprintf('Scopes not allowed for this client: %s', implode(', ', $disallowedScopes))
            );
        }

        return $requestedScopes;
    }
}
