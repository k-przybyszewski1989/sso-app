<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class EntityNotFoundException extends RuntimeException
{
    public function __construct(string $entityName, string $identifier)
    {
        parent::__construct(sprintf('%s not found: %s', $entityName, $identifier));
    }
}
