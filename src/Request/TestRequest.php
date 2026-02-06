<?php

declare(strict_types=1);

namespace App\Request;

final readonly class TestRequest
{
    public function __construct(
        public string $name,
    ) {
    }
}
