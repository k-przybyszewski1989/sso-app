<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\OAuth2ClientRepositoryInterface;
use App\Request\OAuth2\AuthorizationRequest;
use App\Request\OAuth2\IntrospectRequest;
use App\Request\OAuth2\RevokeRequest;
use App\Request\OAuth2\TokenRequest;
use App\Request\ParamConverter\RequestTransform;
use App\Service\OAuth2\AccessTokenServiceInterface;
use App\Service\OAuth2\AuthorizationCodeServiceInterface;
use App\Service\OAuth2\OAuth2ServiceInterface;
use App\Service\OAuth2\RefreshTokenServiceInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class OAuth2Controller extends AbstractController
{
    public function __construct(
        private readonly OAuth2ServiceInterface $oAuth2Service,
        private readonly AuthorizationCodeServiceInterface $authorizationCodeService,
        private readonly AccessTokenServiceInterface $accessTokenService,
        private readonly RefreshTokenServiceInterface $refreshTokenService,
        private readonly OAuth2ClientRepositoryInterface $clientRepository,
    ) {
    }

    #[Route('/oauth2/authorize', name: 'oauth2_authorize', methods: ['POST'])]
    public function authorize(
        #[RequestTransform(validate: true)]
        AuthorizationRequest $request,
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        // Get client
        $client = $this->clientRepository->getByClientId($request->clientId);

        // Parse scopes
        $scopes = $request->scope ? explode(' ', $request->scope) : [];

        // Create authorization code
        $authCode = $this->authorizationCodeService->createAuthorizationCode(
            $client,
            $user,
            $request->redirectUri,
            $scopes,
            $request->codeChallenge,
            $request->codeChallengeMethod,
        );

        // Return code as JSON (API-only endpoint)
        $response = [
            'code' => $authCode->getCode(),
        ];

        if (null !== $request->state) {
            $response['state'] = $request->state;
        }

        return new JsonResponse($response);
    }

    #[Route('/oauth2/token', name: 'oauth2_token', methods: ['POST'])]
    public function token(
        #[RequestTransform(validate: true)]
        TokenRequest $request,
    ): JsonResponse {
        $tokenResponse = $this->oAuth2Service->issueToken($request);

        return new JsonResponse($tokenResponse->toArray());
    }

    #[Route('/oauth2/revoke', name: 'oauth2_revoke', methods: ['POST'])]
    public function revoke(
        #[RequestTransform(validate: true)]
        RevokeRequest $request,
    ): JsonResponse {
        // Try to revoke as access token first, then as refresh token
        try {
            if ('refresh_token' === $request->tokenTypeHint) {
                $this->refreshTokenService->revokeToken($request->token);
            } else {
                // Default to access_token or try both if hint is null
                try {
                    $this->accessTokenService->revokeToken($request->token);
                } catch (Exception $e) {
                    // If access token revocation fails and no hint, try refresh token
                    if (null === $request->tokenTypeHint) {
                        $this->refreshTokenService->revokeToken($request->token);
                    } else {
                        throw $e;
                    }
                }
            }
        } catch (Exception) {
            // RFC 7009: The authorization server responds with HTTP status code 200
            // regardless of whether the token was successfully revoked
        }

        return new JsonResponse(null, Response::HTTP_OK);
    }

    #[Route('/oauth2/introspect', name: 'oauth2_introspect', methods: ['POST'])]
    public function introspect(
        #[RequestTransform(validate: true)]
        IntrospectRequest $request,
    ): JsonResponse {
        try {
            $accessToken = $this->accessTokenService->validateToken($request->token);

            $response = [
                'active' => true,
                'scope' => implode(' ', $accessToken->getScopes()),
                'client_id' => $accessToken->getClient()->getClientId(),
                'token_type' => 'Bearer',
                'exp' => $accessToken->getExpiresAt()->getTimestamp(),
                'iat' => $accessToken->getCreatedAt()->getTimestamp(),
            ];

            if (null !== $accessToken->getUser()) {
                $response['username'] = $accessToken->getUser()->getUsername();
                $response['sub'] = (string) $accessToken->getUser()->getId();
            }

            return new JsonResponse($response);
        } catch (Exception) {
            // Token is invalid, expired, or revoked
            return new JsonResponse(['active' => false]);
        }
    }
}
