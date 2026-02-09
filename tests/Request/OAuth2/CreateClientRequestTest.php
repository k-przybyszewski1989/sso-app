<?php

declare(strict_types=1);

namespace App\Tests\Request\OAuth2;

use App\Enum\GrantType;
use App\Request\OAuth2\CreateClientRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateClientRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidRequestWithAllFields(): void
    {
        $request = new CreateClientRequest(
            name: 'Test Client',
            redirectUris: ['https://example.com/callback'],
            grantTypes: [GrantType::AUTHORIZATION_CODE],
            confidential: true,
            description: 'Test description'
        );

        $violations = $this->validator->validate($request);

        $this->assertCount(0, $violations);
    }

    public function testNameCannotBeBlank(): void
    {
        $request = new CreateClientRequest(
            name: '',
            redirectUris: ['https://example.com/callback'],
            grantTypes: [GrantType::AUTHORIZATION_CODE]
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
        $firstViolation = $violations->get(0);
        $this->assertInstanceOf(\Symfony\Component\Validator\ConstraintViolationInterface::class, $firstViolation);
        $message = $firstViolation->getMessage();
        $this->assertStringContainsString('blank', strtolower((string) $message));
    }

    public function testNameMustBeAtLeast3Characters(): void
    {
        $request = new CreateClientRequest(
            name: 'ab',
            redirectUris: ['https://example.com/callback'],
            grantTypes: [GrantType::AUTHORIZATION_CODE]
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());

        $hasLengthViolation = false;
        foreach ($violations as $violation) {
            $message = strtolower((string) $violation->getMessage());
            if (str_contains($message, 'at least')
                || str_contains($message, 'minimum')
                || str_contains($message, 'too short')
                || str_contains($message, 'characters long')) {
                $hasLengthViolation = true;
                break;
            }
        }

        $firstViolation = $violations->get(0);
        $errorMessage = 'Expected a minimum length violation, got: ' . (string) $firstViolation->getMessage();
        $this->assertTrue($hasLengthViolation, $errorMessage);
    }

    public function testNameCannotExceed255Characters(): void
    {
        $request = new CreateClientRequest(
            name: str_repeat('a', 256),
            redirectUris: ['https://example.com/callback'],
            grantTypes: [GrantType::AUTHORIZATION_CODE]
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());

        $hasLengthViolation = false;
        foreach ($violations as $violation) {
            $message = strtolower((string) $violation->getMessage());
            if (str_contains($message, 'cannot be longer')
                || str_contains($message, 'at most')
                || str_contains($message, 'maximum')
                || str_contains($message, 'too long')
                || str_contains($message, 'characters long')) {
                $hasLengthViolation = true;
                break;
            }
        }

        $firstViolation = $violations->get(0);
        $errorMessage = 'Expected a maximum length violation, got: ' . (string) $firstViolation->getMessage();
        $this->assertTrue($hasLengthViolation, $errorMessage);
    }

    public function testRedirectUrisCannotBeEmpty(): void
    {
        $request = new CreateClientRequest(
            name: 'Test Client',
            redirectUris: [],
            grantTypes: [GrantType::AUTHORIZATION_CODE]
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function testRedirectUrisMustBeValidUrls(): void
    {
        $request = new CreateClientRequest(
            name: 'Test Client',
            redirectUris: ['not-a-url', 'also-invalid'],
            grantTypes: [GrantType::AUTHORIZATION_CODE]
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());

        $hasUrlViolation = false;
        foreach ($violations as $violation) {
            if (str_contains(strtolower((string) $violation->getMessage()), 'url')) {
                $hasUrlViolation = true;
                break;
            }
        }

        $this->assertTrue($hasUrlViolation, 'Expected a URL validation violation');
    }

    public function testGrantTypesCannotBeEmpty(): void
    {
        $request = new CreateClientRequest(
            name: 'Test Client',
            redirectUris: ['https://example.com/callback'],
            grantTypes: []
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
    }

    // Note: Invalid grant type validation now happens at Symfony's deserialization layer
    // when converting strings to GrantType enum, not at the validator level.
    // This test is no longer applicable as the Assert\Choice constraint was removed.

    public function testConfidentialDefaultsToTrue(): void
    {
        $request = new CreateClientRequest(
            name: 'Test Client',
            redirectUris: ['https://example.com/callback'],
            grantTypes: [GrantType::AUTHORIZATION_CODE]
        );

        $this->assertTrue($request->confidential);
    }

    public function testDescriptionCanBeNull(): void
    {
        $request = new CreateClientRequest(
            name: 'Test Client',
            redirectUris: ['https://example.com/callback'],
            grantTypes: [GrantType::AUTHORIZATION_CODE],
            confidential: true,
            description: null
        );

        $violations = $this->validator->validate($request);

        $this->assertCount(0, $violations);
    }

    public function testValidRequestWithMultipleRedirectUrisAndGrantTypes(): void
    {
        $request = new CreateClientRequest(
            name: 'Complex Client',
            redirectUris: [
                'https://example.com/callback',
                'https://app.example.com/redirect',
                'https://mobile.example.com/oauth',
            ],
            grantTypes: [GrantType::AUTHORIZATION_CODE, GrantType::CLIENT_CREDENTIALS, GrantType::REFRESH_TOKEN],
            confidential: false
        );

        $violations = $this->validator->validate($request);

        $this->assertCount(0, $violations);
    }
}
