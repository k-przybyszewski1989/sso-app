<?php

declare(strict_types=1);

namespace App\Security\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class RequireScope
{
    /**
     * @var array<string>
     */
    public array $scopes;

    /**
     * @param array<string>|string $scopes Required scope(s) for accessing this resource
     */
    public function __construct(string|array $scopes)
    {
        $this->scopes = is_array($scopes) ? $scopes : [$scopes];
    }

    /**
     * @return array<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }
}
