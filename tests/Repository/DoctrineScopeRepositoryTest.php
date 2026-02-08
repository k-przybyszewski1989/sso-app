<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Scope;
use App\Exception\EntityNotFoundException;
use App\Repository\ScopeRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineScopeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ScopeRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(ScopeRepositoryInterface::class);

        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testSaveAndFindById(): void
    {
        $scope = new Scope('test.scope', 'Test scope description');
        $this->repository->save($scope);
        $this->entityManager->flush();

        $foundScope = $this->repository->findById($scope->getId());

        $this->assertNotNull($foundScope);
        $this->assertSame($scope->getId(), $foundScope->getId());
        $this->assertSame('test.scope', $foundScope->getIdentifier());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findById(999999);

        $this->assertNull($result);
    }

    public function testGetById(): void
    {
        $scope = new Scope('get.scope', 'Get scope description');
        $this->repository->save($scope);
        $this->entityManager->flush();

        $foundScope = $this->repository->getById($scope->getId());

        $this->assertSame($scope->getId(), $foundScope->getId());
        $this->assertSame('get.scope', $foundScope->getIdentifier());
    }

    public function testGetByIdThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Scope not found: 999999');

        $this->repository->getById(999999);
    }

    public function testFindByIdentifier(): void
    {
        $scope = new Scope('unique.identifier', 'Unique scope');
        $this->repository->save($scope);
        $this->entityManager->flush();

        $foundScope = $this->repository->findByIdentifier('unique.identifier');

        $this->assertNotNull($foundScope);
        $this->assertSame($scope->getId(), $foundScope->getId());
        $this->assertSame('unique.identifier', $foundScope->getIdentifier());
    }

    public function testFindByIdentifierReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByIdentifier('nonexistent.scope');

        $this->assertNull($result);
    }

    public function testGetByIdentifier(): void
    {
        $scope = new Scope('getby.identifier', 'Get by identifier scope');
        $this->repository->save($scope);
        $this->entityManager->flush();

        $foundScope = $this->repository->getByIdentifier('getby.identifier');

        $this->assertSame($scope->getId(), $foundScope->getId());
        $this->assertSame('getby.identifier', $foundScope->getIdentifier());
    }

    public function testGetByIdentifierThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Scope not found: missing.scope');

        $this->repository->getByIdentifier('missing.scope');
    }

    public function testFindAll(): void
    {
        $scope1 = new Scope('scope.one', 'First scope');
        $scope2 = new Scope('scope.two', 'Second scope');

        $this->repository->save($scope1);
        $this->repository->save($scope2);
        $this->entityManager->flush();

        $scopes = $this->repository->findAll();

        $this->assertGreaterThanOrEqual(2, count($scopes));
        $this->assertContainsOnlyInstancesOf(Scope::class, $scopes);
    }

    public function testFindDefaults(): void
    {
        $defaultScope = new Scope('default.scope', 'Default scope', true);
        $nonDefaultScope = new Scope('nondefault.scope', 'Non-default scope', false);

        $this->repository->save($defaultScope);
        $this->repository->save($nonDefaultScope);
        $this->entityManager->flush();

        $defaultScopes = $this->repository->findDefaults();

        $foundDefaultScope = false;
        $foundNonDefaultScope = false;

        foreach ($defaultScopes as $scope) {
            if ('default.scope' === $scope->getIdentifier()) {
                $foundDefaultScope = true;
                $this->assertTrue($scope->isDefault());
            }
            if ('nondefault.scope' === $scope->getIdentifier()) {
                $foundNonDefaultScope = true;
            }
        }

        $this->assertTrue($foundDefaultScope);
        $this->assertFalse($foundNonDefaultScope);
    }

    public function testFindByIdentifiers(): void
    {
        $scope1 = new Scope('find.one', 'Find one');
        $scope2 = new Scope('find.two', 'Find two');
        $scope3 = new Scope('find.three', 'Find three');

        $this->repository->save($scope1);
        $this->repository->save($scope2);
        $this->repository->save($scope3);
        $this->entityManager->flush();

        $scopes = $this->repository->findByIdentifiers(['find.one', 'find.two']);

        $this->assertCount(2, $scopes);
        $identifiers = array_map(fn (Scope $scope) => $scope->getIdentifier(), $scopes);
        $this->assertContains('find.one', $identifiers);
        $this->assertContains('find.two', $identifiers);
        $this->assertNotContains('find.three', $identifiers);
    }

    public function testFindByIdentifiersReturnsEmptyArrayForNonexistentIdentifiers(): void
    {
        $scopes = $this->repository->findByIdentifiers(['nonexistent.one', 'nonexistent.two']);

        $this->assertCount(0, $scopes);
    }

    public function testDelete(): void
    {
        $scope = new Scope('delete.scope', 'Delete scope');
        $this->repository->save($scope);
        $this->entityManager->flush();
        $scopeId = $scope->getId();

        $this->repository->delete($scope);
        $this->entityManager->flush();

        $result = $this->repository->findById($scopeId);
        $this->assertNull($result);
    }

    public function testGetByIdentifierWithLock(): void
    {
        $scope = new Scope('lock.scope', 'Lock scope');
        $this->repository->save($scope);
        $this->entityManager->flush();

        $foundScope = $this->repository->getByIdentifier('lock.scope', true);

        $this->assertSame($scope->getId(), $foundScope->getId());
    }
}
