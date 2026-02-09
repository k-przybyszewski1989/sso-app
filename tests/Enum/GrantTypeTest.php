<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\GrantType;
use PHPUnit\Framework\TestCase;
use ValueError;

final class GrantTypeTest extends TestCase
{
    public function testFromStringArrayConvertsValidStringsToEnums(): void
    {
        $strings = ['authorization_code', 'client_credentials', 'refresh_token'];

        $enums = GrantType::fromStringArray($strings);

        $this->assertCount(3, $enums);
        $this->assertSame(GrantType::AUTHORIZATION_CODE, $enums[0]);
        $this->assertSame(GrantType::CLIENT_CREDENTIALS, $enums[1]);
        $this->assertSame(GrantType::REFRESH_TOKEN, $enums[2]);
    }

    public function testFromStringArrayWithSingleValue(): void
    {
        $strings = ['client_credentials'];

        $enums = GrantType::fromStringArray($strings);

        $this->assertCount(1, $enums);
        $this->assertSame(GrantType::CLIENT_CREDENTIALS, $enums[0]);
    }

    public function testFromStringArrayWithEmptyArray(): void
    {
        $enums = GrantType::fromStringArray([]);

        $this->assertCount(0, $enums);
        $this->assertSame([], $enums);
    }

    public function testFromStringArrayThrowsExceptionForInvalidValue(): void
    {
        $strings = ['invalid_grant'];

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('"invalid_grant" is not a valid backing value for enum App\Enum\GrantType');

        GrantType::fromStringArray($strings);
    }

    public function testFromStringArrayThrowsExceptionWhenInvalidValueInArray(): void
    {
        $strings = ['authorization_code', 'invalid_grant', 'refresh_token'];

        $this->expectException(ValueError::class);

        GrantType::fromStringArray($strings);
    }

    public function testToStringArrayConvertsEnumsToStrings(): void
    {
        $enums = [
            GrantType::AUTHORIZATION_CODE,
            GrantType::CLIENT_CREDENTIALS,
            GrantType::REFRESH_TOKEN,
        ];

        $strings = GrantType::toStringArray($enums);

        $this->assertCount(3, $strings);
        $this->assertSame('authorization_code', $strings[0]);
        $this->assertSame('client_credentials', $strings[1]);
        $this->assertSame('refresh_token', $strings[2]);
    }

    public function testToStringArrayWithSingleValue(): void
    {
        $enums = [GrantType::REFRESH_TOKEN];

        $strings = GrantType::toStringArray($enums);

        $this->assertCount(1, $strings);
        $this->assertSame('refresh_token', $strings[0]);
    }

    public function testToStringArrayWithEmptyArray(): void
    {
        $strings = GrantType::toStringArray([]);

        $this->assertCount(0, $strings);
        $this->assertSame([], $strings);
    }

    public function testRoundTripConversionPreservesValues(): void
    {
        $originalStrings = ['authorization_code', 'refresh_token'];

        $enums = GrantType::fromStringArray($originalStrings);
        $resultStrings = GrantType::toStringArray($enums);

        $this->assertSame($originalStrings, $resultStrings);
    }

    public function testFromStringArrayPreservesArrayKeys(): void
    {
        $strings = [
            'first' => 'authorization_code',
            'second' => 'client_credentials',
        ];

        $enums = GrantType::fromStringArray($strings);

        $this->assertArrayHasKey('first', $enums);
        $this->assertArrayHasKey('second', $enums);
        $this->assertSame(GrantType::AUTHORIZATION_CODE, $enums['first']);
        $this->assertSame(GrantType::CLIENT_CREDENTIALS, $enums['second']);
    }

    public function testToStringArrayPreservesArrayKeys(): void
    {
        $enums = [
            'first' => GrantType::AUTHORIZATION_CODE,
            'second' => GrantType::CLIENT_CREDENTIALS,
        ];

        $strings = GrantType::toStringArray($enums);

        $this->assertArrayHasKey('first', $strings);
        $this->assertArrayHasKey('second', $strings);
        $this->assertSame('authorization_code', $strings['first']);
        $this->assertSame('client_credentials', $strings['second']);
    }

    public function testEnumCasesHaveCorrectBackingValues(): void
    {
        $this->assertSame('authorization_code', GrantType::AUTHORIZATION_CODE->value);
        $this->assertSame('client_credentials', GrantType::CLIENT_CREDENTIALS->value);
        $this->assertSame('refresh_token', GrantType::REFRESH_TOKEN->value);
    }

    public function testAllEnumCasesCanBeConvertedToStringsAndBack(): void
    {
        $allEnums = [
            GrantType::AUTHORIZATION_CODE,
            GrantType::CLIENT_CREDENTIALS,
            GrantType::REFRESH_TOKEN,
        ];

        $strings = GrantType::toStringArray($allEnums);
        $reconvertedEnums = GrantType::fromStringArray($strings);

        $this->assertSame($allEnums, $reconvertedEnums);
    }
}
