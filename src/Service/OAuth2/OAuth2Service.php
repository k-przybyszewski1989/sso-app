<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Enum\GrantType;
use App\Exception\OAuth2\UnsupportedGrantTypeException;
use App\Request\OAuth2\TokenRequest;
use App\Response\OAuth2\TokenResponse;
use App\Service\OAuth2\Grant\GrantHandlerInterface;
use Traversable;

final readonly class OAuth2Service implements OAuth2ServiceInterface
{
    /**
     * @var array<GrantHandlerInterface>
     */
    private array $grantHandlers;

    /**
     * @param iterable<GrantHandlerInterface> $grantHandlers
     */
    public function __construct(iterable $grantHandlers)
    {
        $this->grantHandlers = $grantHandlers instanceof Traversable
            ? iterator_to_array($grantHandlers)
            : $grantHandlers;
    }

    /**
     * {@inheritDoc}
     */
    public function issueToken(TokenRequest $request): TokenResponse
    {
        foreach ($this->grantHandlers as $handler) {
            if ($handler->supports($request->grantType->value)) {
                return $handler->handle($request);
            }
        }

        throw new UnsupportedGrantTypeException(
            sprintf('Grant type "%s" is not supported', $request->grantType->value)
        );
    }
}
