<?php

declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Request\OAuth2\TokenRequest;
use App\Response\OAuth2\TokenResponse;

interface OAuth2ServiceInterface
{
    /**
     * Issues a token based on the provided request.
     */
    public function issueToken(TokenRequest $request): TokenResponse;
}
