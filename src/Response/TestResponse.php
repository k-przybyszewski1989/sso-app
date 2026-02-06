<?php

declare(strict_types=1);

namespace App\Response;

final readonly class TestResponse
{
    public function __construct(public string $name, public object $t) {}
}
