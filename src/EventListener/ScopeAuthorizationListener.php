<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AccessToken;
use App\Security\Attribute\RequireScope;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class ScopeAuthorizationListener implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        // Controller can be a class or a Closure
        if (!is_array($controller)) {
            return;
        }

        [$controllerObject, $methodName] = $controller;

        // Ensure we have proper types
        if (!is_object($controllerObject) || !is_string($methodName)) {
            return;
        }

        // Get required scopes from method attribute
        $requiredScopes = $this->getRequiredScopes($controllerObject, $methodName);

        if (empty($requiredScopes)) {
            return;
        }

        // Get current security token
        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            $this->logger->warning('Scope authorization failed: no security token present');
            $event->setController(fn () => $this->createForbiddenResponse('Authentication required'));

            return;
        }

        // Get access token from request attributes
        $request = $event->getRequest();
        $accessToken = $request->attributes->get('oauth2_access_token');

        if (!$accessToken instanceof AccessToken) {
            $this->logger->warning('Scope authorization failed: no access token in request context');
            $event->setController(fn () => $this->createForbiddenResponse('Invalid authentication'));

            return;
        }

        // Check if access token has required scopes
        $tokenScopes = $accessToken->getScopes();

        if (!$this->hasRequiredScopes($tokenScopes, $requiredScopes)) {
            $this->logger->warning('Scope authorization failed: insufficient scopes', [
                'required' => $requiredScopes,
                'provided' => $tokenScopes,
                'userId' => $accessToken->getUser()?->getId(),
                'clientId' => $accessToken->getClient()->getClientId(),
            ]);

            $event->setController(fn () => $this->createForbiddenResponse(
                'Insufficient scope',
                [
                    'required_scopes' => $requiredScopes,
                    'provided_scopes' => $tokenScopes,
                ]
            ));
        }
    }

    /**
     * @return array<string>
     */
    private function getRequiredScopes(object $controller, string $methodName): array
    {
        $reflectionClass = new ReflectionClass($controller);
        $reflectionMethod = $reflectionClass->getMethod($methodName);

        // Check method-level attribute first (takes precedence)
        $methodAttributes = $reflectionMethod->getAttributes(RequireScope::class);

        if (!empty($methodAttributes)) {
            $requireScope = $methodAttributes[0]->newInstance();

            return $requireScope->getScopes();
        }

        // Check class-level attribute
        $classAttributes = $reflectionClass->getAttributes(RequireScope::class);

        if (!empty($classAttributes)) {
            $requireScope = $classAttributes[0]->newInstance();

            return $requireScope->getScopes();
        }

        return [];
    }

    /**
     * @param array<string> $tokenScopes
     * @param array<string> $requiredScopes
     */
    private function hasRequiredScopes(array $tokenScopes, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $requiredScope) {
            if (!in_array($requiredScope, $tokenScopes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function createForbiddenResponse(string $message, array $details = []): JsonResponse
    {
        $response = ['error' => 'insufficient_scope', 'error_description' => $message];

        if (!empty($details)) {
            $response = array_merge($response, $details);
        }

        return new JsonResponse($response, Response::HTTP_FORBIDDEN);
    }
}
