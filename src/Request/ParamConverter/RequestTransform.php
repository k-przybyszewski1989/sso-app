<?php

declare(strict_types=1);

namespace App\Request\ParamConverter;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class RequestTransform
{
    public function __construct(
        public bool $validate = true,
    ) {
    }
}
