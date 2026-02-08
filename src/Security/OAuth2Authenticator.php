<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\OAuth2\AccessTokenServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class OAuth2Authenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AccessTokenServiceInterface $accessTokenService,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(Request $request): ?bool
    {
        $authHeader = $request->headers->get('Authorization');

        if (null === $authHeader) {
            return null;
        }

        return str_starts_with($authHeader, 'Bearer ');
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');

        if (null === $authHeader) {
            throw new AuthenticationException('No Authorization header provided');
        }

        // Extract token from "Bearer <token>"
        $token = substr($authHeader, 7);

        if ('' === $token) {
            throw new AuthenticationException('Invalid Authorization header format');
        }

        // Validate access token
        $accessToken = $this->accessTokenService->validateToken($token);

        // Get user from access token
        $user = $accessToken->getUser();

        if (null === $user) {
            throw new AuthenticationException('Access token has no associated user');
        }

        // Store access token in request attributes for scope validation
        $request->attributes->set('oauth2_access_token', $accessToken);

        // Create passport with user badge
        $passport = new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn () => $user)
        );

        return $passport;
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Return null to continue the request
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'error' => 'invalid_token',
            'error_description' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
