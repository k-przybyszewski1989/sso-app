<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\OAuth2Client;
use App\Entity\Scope;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EntityMethodsTest extends TestCase
{
    public function testUserSetEmailUpdatesEmail(): void
    {
        $user = new User('original@example.com', 'username', 'password');

        $user->setEmail('updated@example.com');

        $this->assertSame('updated@example.com', $user->getEmail());
    }

    public function testUserSetUsernameUpdatesUsername(): void
    {
        $user = new User('email@example.com', 'original_username', 'password');

        $user->setUsername('updated_username');

        $this->assertSame('updated_username', $user->getUsername());
    }

    public function testUserGetAccessTokensReturnsCollection(): void
    {
        $user = new User('email@example.com', 'username', 'password');

        $accessTokens = $user->getAccessTokens();

        $this->assertCount(0, $accessTokens);
    }

    public function testUserGetRefreshTokensReturnsCollection(): void
    {
        $user = new User('email@example.com', 'username', 'password');

        $refreshTokens = $user->getRefreshTokens();

        $this->assertCount(0, $refreshTokens);
    }

    public function testUserGetAuthorizationCodesReturnsCollection(): void
    {
        $user = new User('email@example.com', 'username', 'password');

        $authorizationCodes = $user->getAuthorizationCodes();

        $this->assertCount(0, $authorizationCodes);
    }

    public function testUserEraseCredentialsDoesNothing(): void
    {
        $user = new User('email@example.com', 'username', 'password');

        $user->eraseCredentials();

        // Method exists and executes without error
        $this->assertSame('email@example.com', $user->getEmail());
    }

    public function testOAuth2ClientSetClientIdUpdatesClientId(): void
    {
        $client = new OAuth2Client('original_client_id', 'secret_hash', 'Client Name');
        $originalUpdatedAt = $client->getUpdatedAt();

        sleep(1); // Ensure timestamp difference
        $client->setClientId('updated_client_id');

        $this->assertSame('updated_client_id', $client->getClientId());
        $this->assertGreaterThan($originalUpdatedAt, $client->getUpdatedAt());
    }

    public function testOAuth2ClientSetNameUpdatesName(): void
    {
        $client = new OAuth2Client('client_id', 'secret_hash', 'Original Name');
        $originalUpdatedAt = $client->getUpdatedAt();

        sleep(1);
        $client->setName('Updated Name');

        $this->assertSame('Updated Name', $client->getName());
        $this->assertGreaterThan($originalUpdatedAt, $client->getUpdatedAt());
    }

    public function testOAuth2ClientSetDescriptionUpdatesDescription(): void
    {
        $client = new OAuth2Client('client_id', 'secret_hash', 'Client Name');

        $client->setDescription('New description');

        $this->assertSame('New description', $client->getDescription());
    }

    public function testOAuth2ClientGetAccessTokensReturnsCollection(): void
    {
        $client = new OAuth2Client('client_id', 'secret_hash', 'Client Name');

        $accessTokens = $client->getAccessTokens();

        $this->assertCount(0, $accessTokens);
    }

    public function testOAuth2ClientGetRefreshTokensReturnsCollection(): void
    {
        $client = new OAuth2Client('client_id', 'secret_hash', 'Client Name');

        $refreshTokens = $client->getRefreshTokens();

        $this->assertCount(0, $refreshTokens);
    }

    public function testOAuth2ClientGetAuthorizationCodesReturnsCollection(): void
    {
        $client = new OAuth2Client('client_id', 'secret_hash', 'Client Name');

        $authorizationCodes = $client->getAuthorizationCodes();

        $this->assertCount(0, $authorizationCodes);
    }

    public function testOAuth2ClientGetPasswordReturnsClientSecretHash(): void
    {
        $client = new OAuth2Client('client_id', 'secret_hash_value', 'Client Name');

        $password = $client->getPassword();

        $this->assertSame('secret_hash_value', $password);
    }

    public function testScopeSetIdentifierUpdatesIdentifier(): void
    {
        $scope = new Scope('original_identifier');

        $scope->setIdentifier('updated_identifier');

        $this->assertSame('updated_identifier', $scope->getIdentifier());
    }

    public function testScopeGetDescriptionReturnsDescription(): void
    {
        $scope = new Scope('identifier', 'Test description');

        $description = $scope->getDescription();

        $this->assertSame('Test description', $description);
    }

    public function testScopeSetDescriptionUpdatesDescription(): void
    {
        $scope = new Scope('identifier', 'Original description');

        $scope->setDescription('Updated description');

        $this->assertSame('Updated description', $scope->getDescription());
    }

    public function testScopeSetIsDefaultUpdatesIsDefault(): void
    {
        $scope = new Scope('identifier', null, false);

        $scope->setIsDefault(true);

        $this->assertTrue($scope->isDefault());
    }

    public function testScopeGetCreatedAtReturnsCreatedAt(): void
    {
        $before = new DateTimeImmutable();
        $scope = new Scope('identifier');
        $after = new DateTimeImmutable();

        $createdAt = $scope->getCreatedAt();

        $this->assertGreaterThanOrEqual($before, $createdAt);
        $this->assertLessThanOrEqual($after, $createdAt);
    }
}
