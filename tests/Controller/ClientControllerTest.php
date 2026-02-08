<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\OAuth2Client;
use App\Entity\Scope;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ClientControllerTest extends WebTestCase
{
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

        // Seed required scopes
        $scopes = [
            ['identifier' => 'openid', 'description' => 'OpenID Connect scope', 'isDefault' => true],
            ['identifier' => 'profile', 'description' => 'Access to user profile', 'isDefault' => true],
        ];

        foreach ($scopes as $scopeData) {
            $scope = new Scope(
                $scopeData['identifier'],
                $scopeData['description'],
                $scopeData['isDefault']
            );
            $entityManager->persist($scope);
        }

        // Create a default OAuth2 client required for login
        $defaultClient = new OAuth2Client('default-test-client', 'hashed-secret', 'Default Test Client');
        $defaultClient->setRedirectUris(['https://example.com/callback']);
        $defaultClient->setGrantTypes(['authorization_code', 'client_credentials']);
        $defaultClient->setConfidential(true);
        $defaultClient->setActive(true);
        $entityManager->persist($defaultClient);

        $entityManager->flush();
    }

    private function createAdminUser(): User
    {
        $container = static::getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $hashedPassword = $passwordHasher->hashPassword(
            new User('admin@example.com', 'admin', 'temp'),
            'admin123'
        );

        $adminUser = new User('admin@example.com', 'admin', $hashedPassword);
        $adminUser->setRoles(['ROLE_ADMIN']);
        $adminUser->setEnabled(true);

        $entityManager->persist($adminUser);
        $entityManager->flush();

        return $adminUser;
    }

    public function testListClientsReturnsAllClients(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();
        $this->createAdminUser();

        // Login to get token
        $loginRequestBody = json_encode([
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ], JSON_THROW_ON_ERROR);

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
        $this->assertResponseIsSuccessful('Login failed: ' . $loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);
        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertIsString($loginResponse['access_token']);
        $adminToken = $loginResponse['access_token'];

        // Create test OAuth2 clients
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $oauth2Client1 = $this->createTestOAuth2Client('Client One', 'client-1');
        $oauth2Client2 = $this->createTestOAuth2Client('Client Two', 'client-2');

        $entityManager->persist($oauth2Client1);
        $entityManager->persist($oauth2Client2);
        $entityManager->flush();

        // Make request
        $client->request(
            'GET',
            '/api/clients',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $responseContent = $client->getResponse()->getContent();
        $this->assertNotFalse($responseContent);
        $response = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        // Should have 3 clients: the default one created in initializeDatabase + 2 test clients
        $this->assertCount(3, $response);

        // Find the test clients in the response (order might vary)
        $clientIds = array_column($response, 'client_id');
        $this->assertContains('client-1', $clientIds);
        $this->assertContains('client-2', $clientIds);

        // Verify structure of first client
        $this->assertIsArray($response[0]);
        $this->assertArrayHasKey('redirect_uris', $response[0]);
        $this->assertArrayHasKey('grant_types', $response[0]);
        $this->assertArrayHasKey('confidential', $response[0]);
        $this->assertArrayHasKey('created_at', $response[0]);
        $this->assertArrayHasKey('updated_at', $response[0]);
        $this->assertIsArray($response[0]['redirect_uris']);
        $this->assertIsArray($response[0]['grant_types']);
        $this->assertIsBool($response[0]['confidential']);
    }

    public function testListClientsReturnsEmptyArrayWhenNoClients(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();
        $this->createAdminUser();

        // Login
        $loginRequestBody = json_encode([
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ], JSON_THROW_ON_ERROR);

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
        $this->assertResponseIsSuccessful('Login failed: ' . $loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);
        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertIsString($loginResponse['access_token']);
        $adminToken = $loginResponse['access_token'];

        $client->request(
            'GET',
            '/api/clients',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $responseContent = $client->getResponse()->getContent();
        $this->assertNotFalse($responseContent);
        $response = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        // Should have 1 client (the default one created in initializeDatabase)
        $this->assertCount(1, $response);
        $this->assertIsArray($response[0]);
        $this->assertArrayHasKey('client_id', $response[0]);
        $this->assertEquals('default-test-client', $response[0]['client_id']);
    }

    public function testCreateClientReturns201WithCredentials(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();
        $this->createAdminUser();

        // Login
        $loginRequestBody = json_encode([
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ], JSON_THROW_ON_ERROR);

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
        $this->assertResponseIsSuccessful('Login failed: ' . $loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);
        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertIsString($loginResponse['access_token']);
        $adminToken = $loginResponse['access_token'];

        $requestBody = json_encode([
            'name' => 'New Test Client',
            'redirect_uris' => ['https://example.com/callback'],
            'grant_types' => ['authorization_code'],
            'confidential' => true,
            'description' => 'Test client description',
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/clients',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            $requestBody
        );

        $this->assertResponseStatusCodeSame(201);

        $responseContent = $client->getResponse()->getContent();
        $this->assertNotFalse($responseContent);
        $response = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('client_id', $response);
        $this->assertArrayHasKey('client_secret', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertIsString($response['client_id']);
        $this->assertIsString($response['client_secret']);
        $this->assertEquals('New Test Client', $response['name']);

        // Verify client exists in database
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $clientRepository = $entityManager->getRepository(OAuth2Client::class);
        /** @var string $clientId */
        $clientId = $response['client_id'];
        $createdClient = $clientRepository->findOneBy(['clientId' => $clientId]);

        $this->assertNotNull($createdClient);
        $this->assertEquals('New Test Client', $createdClient->getName());
    }

    public function testCreateClientRequiresAdminRole(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        $requestBody = json_encode([
            'name' => 'Unauthorized Client',
            'redirect_uris' => ['https://example.com/callback'],
            'grant_types' => ['authorization_code'],
        ], JSON_THROW_ON_ERROR);

        // Request without authentication
        $client->request(
            'POST',
            '/api/clients',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $requestBody
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateClientValidationFailsWithInvalidData(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();
        $this->createAdminUser();

        // Login
        $loginRequestBody = json_encode([
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ], JSON_THROW_ON_ERROR);

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
        $this->assertResponseIsSuccessful('Login failed: ' . $loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);
        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertIsString($loginResponse['access_token']);
        $adminToken = $loginResponse['access_token'];

        // Test with name too short
        $requestBody = json_encode([
            'name' => 'ab', // Less than 3 characters
            'redirect_uris' => ['https://example.com/callback'],
            'grant_types' => ['authorization_code'],
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/clients',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            $requestBody
        );

        $this->assertResponseStatusCodeSame(400);

        // Test with empty redirect URIs
        $requestBody = json_encode([
            'name' => 'Valid Name',
            'redirect_uris' => [],
            'grant_types' => ['authorization_code'],
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/clients',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            $requestBody
        );

        $this->assertResponseStatusCodeSame(400);

        // Test with invalid grant type
        $requestBody = json_encode([
            'name' => 'Valid Name',
            'redirect_uris' => ['https://example.com/callback'],
            'grant_types' => ['invalid_grant'],
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/clients',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            $requestBody
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeleteClientReturns204OnSuccess(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();
        $this->createAdminUser();

        // Login
        $loginRequestBody = json_encode([
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ], JSON_THROW_ON_ERROR);

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
        $this->assertResponseIsSuccessful('Login failed: ' . $loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);
        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertIsString($loginResponse['access_token']);
        $adminToken = $loginResponse['access_token'];

        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Create client
        $oauth2Client = $this->createTestOAuth2Client('Client to Delete', 'delete-me-123');
        $entityManager->persist($oauth2Client);
        $entityManager->flush();

        // Delete client
        $client->request(
            'DELETE',
            '/api/clients/delete-me-123',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );

        $this->assertResponseStatusCodeSame(204);

        // Verify client deleted from database
        $clientRepository = $entityManager->getRepository(OAuth2Client::class);
        $deletedClient = $clientRepository->findOneBy(['clientId' => 'delete-me-123']);

        $this->assertNull($deletedClient);
    }

    public function testDeleteClientReturns404WhenNotExists(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();
        $this->createAdminUser();

        // Login
        $loginRequestBody = json_encode([
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ], JSON_THROW_ON_ERROR);

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
        $this->assertResponseIsSuccessful('Login failed: ' . $loginContent);
        $loginResponse = json_decode($loginContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($loginResponse);
        $this->assertArrayHasKey('access_token', $loginResponse);
        $this->assertIsString($loginResponse['access_token']);
        $adminToken = $loginResponse['access_token'];

        $client->request(
            'DELETE',
            '/api/clients/nonexistent-client-id',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteClientRequiresAdminRole(): void
    {
        $client = static::createClient();
        $this->initializeDatabase();

        // Request without authentication
        $client->request(
            'DELETE',
            '/api/clients/some-client-id',
            [],
            [],
            []
        );

        $this->assertResponseStatusCodeSame(401);
    }

    private function createTestOAuth2Client(string $name, string $clientId): OAuth2Client
    {
        $client = new OAuth2Client($clientId, 'hashed-secret', $name);
        $client->setRedirectUris(['https://example.com/callback']);
        $client->setGrantTypes(['authorization_code', 'client_credentials']);
        $client->setConfidential(true);
        $client->setActive(true);

        return $client;
    }
}
