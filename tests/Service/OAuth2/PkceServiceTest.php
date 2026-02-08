<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Exception\OAuth2\InvalidRequestException;
use App\Service\OAuth2\PkceService;
use PHPUnit\Framework\TestCase;

final class PkceServiceTest extends TestCase
{
    private PkceService $service;

    protected function setUp(): void
    {
        $this->service = new PkceService();
    }

    public function testValidatePlainMethodWithMatchingVerifier(): void
    {
        $verifier = 'test_verifier';
        $challenge = $verifier;

        $result = $this->service->validate($verifier, $challenge, 'plain');

        $this->assertTrue($result);
    }

    public function testValidatePlainMethodWithMismatchingVerifierReturnsFalse(): void
    {
        $result = $this->service->validate('wrong_verifier', 'test_challenge', 'plain');

        $this->assertFalse($result);
    }

    public function testValidateS256MethodWithMatchingVerifier(): void
    {
        $verifier = 'test_verifier_for_s256';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $result = $this->service->validate($verifier, $challenge, 'S256');

        $this->assertTrue($result);
    }

    public function testValidateS256MethodWithMismatchingVerifierReturnsFalse(): void
    {
        $verifier = 'wrong_verifier';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', 'correct_verifier', true)), '+/', '-_'), '=');

        $result = $this->service->validate($verifier, $challenge, 'S256');

        $this->assertFalse($result);
    }

    public function testValidateWithInvalidMethodThrowsException(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Unsupported code challenge method: invalid_method');

        $this->service->validate('verifier', 'challenge', 'invalid_method');
    }
}
