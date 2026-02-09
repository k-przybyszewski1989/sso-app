<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\Scope;
use App\Entity\User;
use App\Enum\GrantType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ScopeEnforcementTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function initializeDatabase(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Clean up database
        $connection = $entityManager->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $tables = ['access_token', 'refresh_token', 'authorization_code', 'oauth2_client', 'user', 'scope'];
        foreach ($tables as $table) {
            try {
                $connection->executeStatement("TRUNCATE TABLE {$table}");
            } catch (Exception $e) {
                // Table might not exist yet
            }
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        // Seed scopes
        $scopes = [
            ['identifier' => 'openid', 'description' => 'OpenID Connect scope', 'isDefault' => true],
            ['identifier' => 'profile', 'description' => 'Access to user profile', 'isDefault' => true],
            ['identifier' => 'email', 'description' => 'Access to user email', 'isDefault' => true],
            ['identifier' => 'offline_access', 'description' => 'Refresh token access', 'isDefault' => false],
        ];

        foreach ($scopes as $scopeData) {
            $scope = new Scope(
                $scopeData['identifier'],
                $scopeData['description'],
                $scopeData['isDefault']
            );
            $entityManager->persist($scope);
        }
        $entityManager->flush();
    }

    public function testAccessWithSufficientScopes(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid', 'profile', 'email']
        );

        // Create access token with required scopes
        $accessToken = $this->createAccessToken(
            $oauth2Client,
            $user,
            ['openid', 'profile', 'email']
        );

        // Access endpoint that requires 'profile' scope (e.g., /api/users/me)
        $client->request(
            'GET',
            '/api/users/me',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken->getToken(),
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertArrayHasKey('username', $response);
    }

    public function testAccessWithInsufficientScopes(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid', 'profile', 'email']
        );

        // Create access token with only 'openid' scope (missing 'profile' required by /api/users/me)
        $accessToken = $this->createAccessToken(
            $oauth2Client,
            $user,
            ['openid']
        );

        // Attempt to access endpoint that requires 'profile' scope
        $client->request(
            'GET',
            '/api/users/me',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken->getToken(),
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('insufficient_scope', $response['error']);
        $this->assertArrayHasKey('error_description', $response);
    }

    public function testClientCredentialsWithLimitedScopes(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create OAuth2 client with limited allowed scopes
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid'] // Only 'openid' scope allowed
        );

        // Request token with scopes that include unauthorized scope
        $client->request(
            'POST',
            '/oauth2/token',
            [
                'grant_type' => 'client_credentials',
                'scope' => 'openid profile email',
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $this->assertResponseStatusCodeSame(400);

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('invalid_scope', $response['error']);
    }

    public function testAuthorizationCodeWithScopeValidation(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Create OAuth2 client with limited scopes
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['authorization_code'],
            ['openid', 'profile'] // Only openid and profile allowed
        );

        // Login user
        $loginRequestBody = json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR);
        $this->assertNotFalse($loginRequestBody);

        $client->request(
            'POST',
            '/api/users/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $loginRequestBody
        );

        $loginContent = $client->getResponse()->getContent();
        $this->assertNotFalse($loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);
        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertIsString($loginResponse['access_token']);
        $userAccessToken = $loginResponse['access_token'];

        // Request authorization code with unauthorized scope
        $authorizeRequestBody = json_encode([
            'response_type' => 'code',
            'client_id' => $oauth2Client->getClientId(),
            'redirect_uri' => 'https://example.com/callback',
            'scope' => 'openid profile email', // 'email' not allowed
            'state' => 'random-state',
        ], JSON_THROW_ON_ERROR);
        $this->assertNotFalse($authorizeRequestBody);

        $client->request(
            'POST',
            '/oauth2/authorize',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userAccessToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            $authorizeRequestBody
        );

        $this->assertResponseStatusCodeSame(400);

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('invalid_scope', $response['error']);
    }

    public function testMultipleScopesRequired(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid', 'profile', 'email']
        );

        // Create access token with only partial scopes
        $accessToken = $this->createAccessToken(
            $oauth2Client,
            $user,
            ['openid', 'profile'] // Missing 'email' if endpoint requires all three
        );

        // This tests that the scope enforcement system works correctly
        // The /api/users/me endpoint should require 'profile' scope at minimum
        $client->request(
            'GET',
            '/api/users/me',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken->getToken(),
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        // Since we have 'profile' scope, this should succeed
        $this->assertResponseIsSuccessful();
    }

    public function testNoScopeRequired(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid']
        );

        // Request token (no specific scope enforcement on token endpoint)
        $client->request(
            'POST',
            '/oauth2/token',
            [
                'grant_type' => 'client_credentials',
                'scope' => 'openid',
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('access_token', $response);
    }

    private function createUser(string $email, string $username, string $password): User
    {
        $container = static::getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $hashedPassword = $passwordHasher->hashPassword(
            new User($email, $username, 'temp'),
            $password
        );

        $user = new User($email, $username, $hashedPassword);
        $user->setRoles(['ROLE_USER']);
        $user->setEnabled(true);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    /**
     * @param array<string> $grantTypes
     * @param array<string> $allowedScopes
     */
    private function createOAuth2Client(
        string $name,
        array $grantTypes,
        array $allowedScopes,
    ): OAuth2Client {
        $container = static::getContainer();
        $clientPasswordHasher = $container->get('security.password_hasher_factory')->getPasswordHasher(OAuth2Client::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $clientId = bin2hex(random_bytes(16));
        $clientSecretHash = $clientPasswordHasher->hash('test-secret');

        $client = new OAuth2Client($clientId, $clientSecretHash, $name);
        $client->setRedirectUris(['https://example.com/callback']);
        $client->setGrantTypes(GrantType::fromStringArray($grantTypes));
        $client->setAllowedScopes($allowedScopes);
        $client->setConfidential(true);
        $client->setActive(true);

        $entityManager->persist($client);
        $entityManager->flush();

        return $client;
    }

    /**
     * @param array<string> $scopes
     */
    private function createAccessToken(
        OAuth2Client $client,
        User $user,
        array $scopes,
    ): AccessToken {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $tokenString = bin2hex(random_bytes(32));
        $expiresAt = new DateTimeImmutable('+1 hour');

        $token = new AccessToken($tokenString, $client, $expiresAt);
        $token->setUser($user);
        $token->setScopes($scopes);
        $token->setRevoked(false);

        $entityManager->persist($token);
        $entityManager->flush();

        return $token;
    }
}
