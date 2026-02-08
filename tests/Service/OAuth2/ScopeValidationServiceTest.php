<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Entity\Scope;
use App\Exception\OAuth2\InvalidScopeException;
use App\Repository\ScopeRepositoryInterface;
use App\Service\OAuth2\ScopeValidationService;
use PHPUnit\Framework\TestCase;

final class ScopeValidationServiceTest extends TestCase
{
    public function testValidateWithEmptyRequestedScopesReturnsEmptyArray(): void
    {
        $scopeRepository = $this->createMock(ScopeRepositoryInterface::class);
        $scopeRepository->expects($this->never())
            ->method('findByIdentifiers');

        $service = new ScopeValidationService($scopeRepository);
        $result = $service->validate([], ['read', 'write']);

        $this->assertSame([], $result);
    }

    public function testValidateWithValidScopesReturnsRequestedScopes(): void
    {
        $requestedScopes = ['read', 'write'];
        $allowedScopes = ['read', 'write', 'admin'];

        $scope1 = new Scope('read', 'Read access');
        $scope2 = new Scope('write', 'Write access');

        $scopeRepository = $this->createMock(ScopeRepositoryInterface::class);
        $scopeRepository->expects($this->once())
            ->method('findByIdentifiers')
            ->with($requestedScopes)
            ->willReturn([$scope1, $scope2]);

        $service = new ScopeValidationService($scopeRepository);
        $result = $service->validate($requestedScopes, $allowedScopes);

        $this->assertSame($requestedScopes, $result);
    }

    public function testValidateThrowsExceptionWhenScopeDoesNotExist(): void
    {
        $requestedScopes = ['read', 'invalid_scope'];
        $allowedScopes = ['read', 'write'];

        $scope1 = new Scope('read', 'Read access');

        $scopeRepository = $this->createMock(ScopeRepositoryInterface::class);
        $scopeRepository->expects($this->once())
            ->method('findByIdentifiers')
            ->with($requestedScopes)
            ->willReturn([$scope1]);

        $service = new ScopeValidationService($scopeRepository);

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('Invalid scopes requested: invalid_scope');

        $service->validate($requestedScopes, $allowedScopes);
    }

    public function testValidateThrowsExceptionWhenScopeNotAllowedForClient(): void
    {
        $requestedScopes = ['read', 'admin'];
        $allowedScopes = ['read', 'write'];

        $scope1 = new Scope('read', 'Read access');
        $scope2 = new Scope('admin', 'Admin access');

        $scopeRepository = $this->createMock(ScopeRepositoryInterface::class);
        $scopeRepository->expects($this->once())
            ->method('findByIdentifiers')
            ->with($requestedScopes)
            ->willReturn([$scope1, $scope2]);

        $service = new ScopeValidationService($scopeRepository);

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('Scopes not allowed for this client: admin');

        $service->validate($requestedScopes, $allowedScopes);
    }

    public function testValidateThrowsExceptionWhenMultipleScopesNotAllowed(): void
    {
        $requestedScopes = ['read', 'admin', 'delete'];
        $allowedScopes = ['read', 'write'];

        $scope1 = new Scope('read', 'Read access');
        $scope2 = new Scope('admin', 'Admin access');
        $scope3 = new Scope('delete', 'Delete access');

        $scopeRepository = $this->createMock(ScopeRepositoryInterface::class);
        $scopeRepository->expects($this->once())
            ->method('findByIdentifiers')
            ->with($requestedScopes)
            ->willReturn([$scope1, $scope2, $scope3]);

        $service = new ScopeValidationService($scopeRepository);

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('Scopes not allowed for this client:');

        $service->validate($requestedScopes, $allowedScopes);
    }
}
