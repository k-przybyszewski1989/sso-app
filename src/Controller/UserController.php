<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\OAuth2ClientRepositoryInterface;
use App\Request\ParamConverter\RequestTransform;
use App\Request\User\LoginUserRequest;
use App\Request\User\RegisterUserRequest;
use App\Response\User\UserResponse;
use App\Service\OAuth2\AccessTokenServiceInterface;
use App\Service\User\UserAuthenticationServiceInterface;
use App\Service\User\UserRegistrationServiceInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRegistrationServiceInterface $registrationService,
        private readonly UserAuthenticationServiceInterface $authenticationService,
        private readonly AccessTokenServiceInterface $accessTokenService,
        private readonly OAuth2ClientRepositoryInterface $clientRepository,
    ) {
    }

    #[Route('/register', name: 'user_register', methods: ['POST'])]
    public function register(
        #[RequestTransform(validate: true)]
        RegisterUserRequest $request,
    ): JsonResponse {
        $user = $this->registrationService->registerUser(
            $request->email,
            $request->username,
            $request->password,
        );

        $response = new UserResponse(
            $user->getId(),
            $user->getEmail(),
            $user->getUsername(),
            $user->isEnabled(),
            $user->getCreatedAt(),
            $user->getLastLoginAt(),
        );

        return new JsonResponse($response->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'user_login', methods: ['POST'])]
    public function login(
        #[RequestTransform(validate: true)]
        LoginUserRequest $request,
    ): JsonResponse {
        $user = $this->authenticationService->authenticate(
            $request->email,
            $request->password,
        );

        // Create a default internal client for user login if needed
        // For now, we'll get the first available client or require client_id in request
        // This is a simplified implementation - in production you might want a dedicated "first-party" client
        $clients = $this->clientRepository->findActive();

        if (empty($clients)) {
            throw new RuntimeException('No OAuth2 clients available. Please create a client first.');
        }

        $client = $clients[0];

        // Create access token with basic scopes
        $accessToken = $this->accessTokenService->createAccessToken(
            $client,
            ['openid', 'profile', 'email'],
            $user,
        );

        $expiresIn = $accessToken->getExpiresAt()->getTimestamp() - time();

        return new JsonResponse([
            'access_token' => $accessToken->getToken(),
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'scope' => implode(' ', $accessToken->getScopes()),
        ]);
    }

    #[Route('/me', name: 'user_me', methods: ['GET'])]
    public function me(
        #[CurrentUser]
        User $user,
    ): JsonResponse {
        $response = new UserResponse(
            $user->getId(),
            $user->getEmail(),
            $user->getUsername(),
            $user->isEnabled(),
            $user->getCreatedAt(),
            $user->getLastLoginAt(),
        );

        return new JsonResponse($response->toArray());
    }
}
