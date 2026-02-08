<?php

declare(strict_types=1);

namespace App\Enum;

enum GrantType: string
{
    case AUTHORIZATION_CODE = 'authorization_code';
    case CLIENT_CREDENTIALS = 'client_credentials';
    case REFRESH_TOKEN = 'refresh_token';

    /**
     * @param array<string> $grantTypes
     * @return array<self>
     */
    public static function fromStringArray(array $grantTypes): array
    {
        return array_map(
            static fn (string $grantType): self => self::from($grantType),
            $grantTypes
        );
    }

    /**
     * @param array<self> $grantTypes
     * @return array<string>
     */
    public static function toStringArray(array $grantTypes): array
    {
        return array_map(
            static fn (self $grantType): string => $grantType->value,
            $grantTypes
        );
    }
}
