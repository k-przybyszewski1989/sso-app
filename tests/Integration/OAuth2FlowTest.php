<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\OAuth2Client;
use App\Entity\Scope;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OAuth2FlowTest extends WebTestCase
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
            } catch (\Exception $e) {
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

    public function testClientCredentialsFlow(): void
    {
        $browserClient = static::createClient();
        $this->initializeDatabase();

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid', 'profile']
        );

        // Request token
        $browserClient->request(
            'POST',
            '/oauth2/token',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'grant_type' => 'client_credentials',
                'scope' => 'openid profile',
            ])
        );

        $this->assertResponseIsSuccessful();

        $response = json_decode($browserClient->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $response);
        $this->assertArrayHasKey('token_type', $response);
        $this->assertArrayHasKey('expires_in', $response);
        $this->assertArrayHasKey('scope', $response);
        $this->assertEquals('Bearer', $response['token_type']);
        $this->assertEquals(3600, $response['expires_in']);
        $this->assertStringContainsString('openid', $response['scope']);
        $this->assertStringContainsString('profile', $response['scope']);
        $this->assertArrayNotHasKey('refresh_token', $response);
    }

    public function testAuthorizationCodeFlow(): void
    {
        $browserClient = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['authorization_code', 'refresh_token'],
            ['openid', 'profile', 'email', 'offline_access']
        );

        // Login user to get access token
        $browserClient->request(
            'POST',
            '/api/users/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'password123',
            ])
        );

        $this->assertResponseIsSuccessful();
        $loginResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $userAccessToken = $loginResponse['access_token'];

        // Get authorization code
        $browserClient->request(
            'POST',
            '/oauth2/authorize',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userAccessToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'response_type' => 'code',
                'client_id' => $oauth2Client->getClientId(),
                'redirect_uri' => 'https://example.com/callback',
                'scope' => 'openid profile email offline_access',
                'state' => 'random-state',
            ])
        );

        $this->assertResponseIsSuccessful();
        $authResponse = json_decode($browserClient->getResponse()->getContent(), true);

        $this->assertArrayHasKey('code', $authResponse);
        $this->assertArrayHasKey('state', $authResponse);
        $this->assertEquals('random-state', $authResponse['state']);

        $authCode = $authResponse['code'];

        // Exchange code for tokens
        $browserClient->request(
            'POST',
            '/oauth2/token',
            [
                'grant_type' => 'authorization_code',
                'code' => $authCode,
                'redirect_uri' => 'https://example.com/callback',
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $this->assertResponseIsSuccessful();

        $tokenResponse = json_decode($browserClient->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $tokenResponse);
        $this->assertArrayHasKey('token_type', $tokenResponse);
        $this->assertArrayHasKey('expires_in', $tokenResponse);
        $this->assertArrayHasKey('refresh_token', $tokenResponse);
        $this->assertArrayHasKey('scope', $tokenResponse);
        $this->assertEquals('Bearer', $tokenResponse['token_type']);
    }

    public function testRefreshTokenFlow(): void
    {
        $browserClient = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['authorization_code', 'refresh_token'],
            ['openid', 'profile', 'email', 'offline_access']
        );

        // Get initial tokens (via authorization code flow)
        $browserClient->request(
            'POST',
            '/api/users/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'password123',
            ])
        );

        $loginResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $userAccessToken = $loginResponse['access_token'];

        $browserClient->request(
            'POST',
            '/oauth2/authorize',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userAccessToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'response_type' => 'code',
                'client_id' => $oauth2Client->getClientId(),
                'redirect_uri' => 'https://example.com/callback',
                'scope' => 'openid profile email offline_access',
                'state' => 'random-state',
            ])
        );

        $authResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $authCode = $authResponse['code'];

        $browserClient->request(
            'POST',
            '/oauth2/token',
            [
                'grant_type' => 'authorization_code',
                'code' => $authCode,
                'redirect_uri' => 'https://example.com/callback',
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $tokenResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $refreshToken = $tokenResponse['refresh_token'];
        $oldAccessToken = $tokenResponse['access_token'];

        // Use refresh token to get new tokens
        $browserClient->request(
            'POST',
            '/oauth2/token',
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $this->assertResponseIsSuccessful();

        $newTokenResponse = json_decode($browserClient->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $newTokenResponse);
        $this->assertArrayHasKey('refresh_token', $newTokenResponse);
        $this->assertNotEquals($oldAccessToken, $newTokenResponse['access_token']);
        $this->assertNotEquals($refreshToken, $newTokenResponse['refresh_token']);
    }

    public function testPkceAuthorizationCodeFlow(): void
    {
        $browserClient = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['authorization_code', 'refresh_token'],
            ['openid', 'profile', 'email', 'offline_access']
        );

        // Generate PKCE challenge
        $codeVerifier = bin2hex(random_bytes(32));
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        // Login user
        $browserClient->request(
            'POST',
            '/api/users/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'password123',
            ])
        );

        $loginResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $userAccessToken = $loginResponse['access_token'];

        // Get authorization code with PKCE
        $browserClient->request(
            'POST',
            '/oauth2/authorize',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userAccessToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'response_type' => 'code',
                'client_id' => $oauth2Client->getClientId(),
                'redirect_uri' => 'https://example.com/callback',
                'scope' => 'openid profile email offline_access',
                'state' => 'random-state',
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
            ])
        );

        $this->assertResponseIsSuccessful();
        $authResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $authCode = $authResponse['code'];

        // Exchange code for tokens with code_verifier
        $browserClient->request(
            'POST',
            '/oauth2/token',
            [
                'grant_type' => 'authorization_code',
                'code' => $authCode,
                'redirect_uri' => 'https://example.com/callback',
                'code_verifier' => $codeVerifier,
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $this->assertResponseIsSuccessful();

        $tokenResponse = json_decode($browserClient->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $tokenResponse);
        $this->assertArrayHasKey('refresh_token', $tokenResponse);
    }

    public function testTokenRevocation(): void
    {
        $browserClient = static::createClient();
        $this->initializeDatabase();

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['client_credentials'],
            ['openid', 'profile']
        );

        // Get access token
        $browserClient->request(
            'POST',
            '/oauth2/token',
            [
                'grant_type' => 'client_credentials',
                'scope' => 'openid profile',
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $tokenResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $accessToken = $tokenResponse['access_token'];

        // Verify token works
        $browserClient->request(
            'POST',
            '/oauth2/introspect',
            [
                'token' => $accessToken,
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $introspectResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $this->assertTrue($introspectResponse['active']);

        // Revoke token
        $browserClient->request(
            'POST',
            '/oauth2/revoke',
            [
                'token' => $accessToken,
                'token_type_hint' => 'access_token',
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $this->assertResponseIsSuccessful();

        // Verify token is now invalid
        $browserClient->request(
            'POST',
            '/oauth2/introspect',
            [
                'token' => $accessToken,
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $introspectResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $this->assertFalse($introspectResponse['active']);
    }

    public function testTokenIntrospection(): void
    {
        $browserClient = static::createClient();
        $this->initializeDatabase();

        // Create user
        $user = $this->createUser('test@example.com', 'testuser', 'password123');

        // Create OAuth2 client
        $oauth2Client = $this->createOAuth2Client(
            'test-client',
            ['authorization_code'],
            ['openid', 'profile', 'email']
        );

        // Login and get authorization code
        $browserClient->request(
            'POST',
            '/api/users/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'password123',
            ])
        );

        $loginResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $userAccessToken = $loginResponse['access_token'];

        $browserClient->request(
            'POST',
            '/oauth2/authorize',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userAccessToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'response_type' => 'code',
                'client_id' => $oauth2Client->getClientId(),
                'redirect_uri' => 'https://example.com/callback',
                'scope' => 'openid profile email',
                'state' => 'random-state',
            ])
        );

        $authResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $authCode = $authResponse['code'];

        // Exchange code for token
        $browserClient->request(
            'POST',
            '/oauth2/token',
            [
                'grant_type' => 'authorization_code',
                'code' => $authCode,
                'redirect_uri' => 'https://example.com/callback',
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $tokenResponse = json_decode($browserClient->getResponse()->getContent(), true);
        $accessToken = $tokenResponse['access_token'];

        // Introspect token
        $browserClient->request(
            'POST',
            '/oauth2/introspect',
            [
                'token' => $accessToken,
            ],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($oauth2Client->getClientId() . ':test-secret'),
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );

        $this->assertResponseIsSuccessful();

        $introspectResponse = json_decode($browserClient->getResponse()->getContent(), true);

        $this->assertTrue($introspectResponse['active']);
        $this->assertArrayHasKey('scope', $introspectResponse);
        $this->assertArrayHasKey('client_id', $introspectResponse);
        $this->assertArrayHasKey('username', $introspectResponse);
        $this->assertArrayHasKey('exp', $introspectResponse);
        $this->assertEquals($oauth2Client->getClientId(), $introspectResponse['client_id']);
        $this->assertEquals('testuser', $introspectResponse['username']);
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

    private function createOAuth2Client(
        string $name,
        array $grantTypes,
        array $allowedScopes
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
