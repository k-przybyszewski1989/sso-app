<?php

declare(strict_types=1);

namespace App\Response\User;

use DateTimeInterface;

final readonly class UserResponse
{
    public function __construct(
        public int $id,
        public string $email,
        public string $username,
        public bool $enabled,
        public DateTimeInterface $createdAt,
        public ?DateTimeInterface $lastLoginAt = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'enabled' => $this->enabled,
            'created_at' => $this->createdAt->format(DateTimeInterface::ATOM),
            'last_login_at' => $this->lastLoginAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
