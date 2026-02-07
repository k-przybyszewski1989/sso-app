<?php

declare(strict_types=1);

namespace App\Exception\OAuth2;

use RuntimeException;

abstract class OAuth2Exception extends RuntimeException
{
    public function __construct(
        private readonly string $error,
        private readonly string $errorDescription,
        private readonly int $statusCode = 400,
    ) {
        parent::__construct($errorDescription);
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getErrorDescription(): string
    {
        return $this->errorDescription;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'error' => $this->error,
            'error_description' => $this->errorDescription,
        ];
    }
}
