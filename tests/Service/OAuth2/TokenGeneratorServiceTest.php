<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Service\OAuth2\TokenGeneratorService;
use PHPUnit\Framework\TestCase;

final class TokenGeneratorServiceTest extends TestCase
{
    private TokenGeneratorService $service;

    protected function setUp(): void
    {
        $this->service = new TokenGeneratorService();
    }

    public function testGenerateAccessTokenReturnsCorrectLength(): void
    {
        $token = $this->service->generateAccessToken();

        $this->assertSame(64, strlen($token));
    }

    public function testGenerateAccessTokenReturnsUniqueTokens(): void
    {
        $token1 = $this->service->generateAccessToken();
        $token2 = $this->service->generateAccessToken();

        $this->assertNotSame($token1, $token2);
    }

    public function testGenerateAccessTokenReturnsHexString(): void
    {
        $token = $this->service->generateAccessToken();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testGenerateRefreshTokenReturnsCorrectLength(): void
    {
        $token = $this->service->generateRefreshToken();

        $this->assertSame(64, strlen($token));
    }

    public function testGenerateRefreshTokenReturnsUniqueTokens(): void
    {
        $token1 = $this->service->generateRefreshToken();
        $token2 = $this->service->generateRefreshToken();

        $this->assertNotSame($token1, $token2);
    }

    public function testGenerateAuthorizationCodeReturnsCorrectLength(): void
    {
        $code = $this->service->generateAuthorizationCode();

        $this->assertSame(32, strlen($code));
    }

    public function testGenerateAuthorizationCodeReturnsUniqueTokens(): void
    {
        $code1 = $this->service->generateAuthorizationCode();
        $code2 = $this->service->generateAuthorizationCode();

        $this->assertNotSame($code1, $code2);
    }

    public function testGenerateClientIdReturnsCorrectLength(): void
    {
        $clientId = $this->service->generateClientId();

        $this->assertSame(32, strlen($clientId));
    }

    public function testGenerateClientIdReturnsUniqueTokens(): void
    {
        $clientId1 = $this->service->generateClientId();
        $clientId2 = $this->service->generateClientId();

        $this->assertNotSame($clientId1, $clientId2);
    }

    public function testGenerateClientSecretReturnsCorrectLength(): void
    {
        $secret = $this->service->generateClientSecret();

        $this->assertSame(64, strlen($secret));
    }

    public function testGenerateClientSecretReturnsUniqueTokens(): void
    {
        $secret1 = $this->service->generateClientSecret();
        $secret2 = $this->service->generateClientSecret();

        $this->assertNotSame($secret1, $secret2);
    }
}
