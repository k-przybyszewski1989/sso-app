<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\Enum\GrantType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

final class GrantTypeArrayType extends JsonType
{
    public const string NAME = 'grant_type_array';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @param mixed $value
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            return parent::convertToDatabaseValue($value, $platform);
        }

        // Check if we already have strings (shouldn't happen normally, but be defensive)
        if (isset($value[0]) && is_string($value[0])) {
            return parent::convertToDatabaseValue($value, $platform);
        }

        // At this point, we have an array of GrantType enums
        /** @var array<GrantType> $value */
        $stringArray = GrantType::toStringArray($value);

        return parent::convertToDatabaseValue($stringArray, $platform);
    }

    /**
     * @return array<GrantType>|null
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?array
    {
        /** @var array<string> $result */
        $result = parent::convertToPHPValue($value, $platform);

        if (null === $result || [] === $result) {
            return $result;
        }

        if (!is_array($result)) {
            return null;
        }

        // If we already have GrantType enums, return as-is (defensive check)
        if (isset($result[0]) && $result[0] instanceof GrantType) {
            /** @var array<GrantType> $r */
            $r = $result;

            return $this->assertGrantTypeArray($r);
        }

        return $this->convertStringArrayToEnums($result);
    }

    /**
     * @param array<GrantType> $array
     * @return array<GrantType>
     */
    private function assertGrantTypeArray(array $array): array
    {
        return $array;
    }

    /**
     * @param array<string> $strings
     * @return array<GrantType>
     */
    private function convertStringArrayToEnums(array $strings): array
    {
        return GrantType::fromStringArray($strings);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
