<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\OAuth2Client;
use App\Entity\Scope;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFlowTest extends WebTestCase
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

    public function testUserRegistrationAndLogin(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create OAuth2 client for login
        $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid', 'profile', 'email']
        );

        // Register user
        $registerRequestBody = json_encode([
            'email' => 'newuser@example.com',
            'username' => 'newuser',
            'password' => 'SecurePass123!',
        ], JSON_THROW_ON_ERROR);

        $this->assertNotFalse($registerRequestBody);

        $client->request(
            'POST',
            '/api/users/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $registerRequestBody
        );

        $this->assertResponseIsSuccessful();

        $registerContent = $client->getResponse()->getContent();
        $this->assertNotFalse($registerContent);
        $registerResponse = json_decode($registerContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($registerResponse);

        $this->assertArrayHasKey('id', $registerResponse);
        $this->assertArrayHasKey('email', $registerResponse);
        $this->assertArrayHasKey('username', $registerResponse);
        $this->assertEquals('newuser@example.com', $registerResponse['email']);
        $this->assertEquals('newuser', $registerResponse['username']);

        // Login with registered user
        $loginRequestBody = json_encode([
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
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

        $this->assertResponseIsSuccessful();

        $loginContent = $client->getResponse()->getContent();
        $this->assertNotFalse($loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);

        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertArrayHasKey('token_type', $loginResponse);
        $this->assertArrayHasKey('expires_in', $loginResponse);
        $this->assertEquals('Bearer', $loginResponse['token_type']);
    }

    public function testProtectedEndpointAccess(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create OAuth2 client for login
        $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid', 'profile', 'email']
        );

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Login to get access token
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

        $this->assertResponseIsSuccessful();

        $loginContent = $client->getResponse()->getContent();
        $this->assertNotFalse($loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);
        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertIsString($loginResponse['access_token']);
        $accessToken = $loginResponse['access_token'];

        // Access protected endpoint with valid token
        $client->request(
            'GET',
            '/api/users/me',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $this->assertResponseIsSuccessful();

        $userContent = $client->getResponse()->getContent();
        $this->assertNotFalse($userContent);
        $userResponse = json_decode($userContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($userResponse);

        $this->assertArrayHasKey('id', $userResponse);
        $this->assertArrayHasKey('email', $userResponse);
        $this->assertArrayHasKey('username', $userResponse);
        $this->assertEquals('test@example.com', $userResponse['email']);
        $this->assertEquals('testuser', $userResponse['username']);
    }

    public function testUnauthorizedAccess(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Attempt to access protected endpoint without token
        $client->request(
            'GET',
            '/api/users/me',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(401);

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('invalid_token', $response['error']);
    }

    public function testUnauthorizedAccessWithInvalidToken(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Attempt to access protected endpoint with invalid token
        $client->request(
            'GET',
            '/api/users/me',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer invalid-token-here',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(401);

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('error', $response);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create OAuth2 client for login
        $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid', 'profile', 'email']
        );

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Attempt to login with wrong password
        $loginRequestBody = json_encode([
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
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

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRegistrationWithDuplicateEmail(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Attempt to register with same email
        $registerRequestBody = json_encode([
            'email' => 'test@example.com',
            'username' => 'differentuser',
            'password' => 'SecurePass123!',
        ], JSON_THROW_ON_ERROR);
        $this->assertNotFalse($registerRequestBody);

        $client->request(
            'POST',
            '/api/users/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $registerRequestBody
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRegistrationWithDuplicateUsername(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Attempt to register with same username
        $registerRequestBody = json_encode([
            'email' => 'different@example.com',
            'username' => 'testuser',
            'password' => 'SecurePass123!',
        ], JSON_THROW_ON_ERROR);
        $this->assertNotFalse($registerRequestBody);

        $client->request(
            'POST',
            '/api/users/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $registerRequestBody
        );

        $this->assertResponseStatusCodeSame(400);
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
        $client->setGrantTypes($grantTypes);
        $client->setAllowedScopes($allowedScopes);
        $client->setConfidential(true);
        $client->setActive(true);

        $entityManager->persist($client);
        $entityManager->flush();

        return $client;
    }
}
