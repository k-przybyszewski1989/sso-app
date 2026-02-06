<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TestRequest
{
    public function __construct(
        public string $name
    ) {}
}
