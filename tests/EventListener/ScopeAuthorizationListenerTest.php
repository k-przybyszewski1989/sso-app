<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\AccessToken;
use App\Entity\OAuth2Client;
use App\Entity\User;
use App\EventListener\ScopeAuthorizationListener;
use App\Security\Attribute\RequireScope;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class ScopeAuthorizationListenerTest extends TestCase
{
    public function testGetSubscribedEventsReturnsCorrectMapping(): void
    {
        $events = ScopeAuthorizationListener::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::CONTROLLER, $events);
        $this->assertSame('onKernelController', $events[KernelEvents::CONTROLLER]);
    }

    public function testOnKernelControllerDoesNothingWhenControllerIsNotArray(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ScopeAuthorizationListener($tokenStorage, $logger);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new ControllerEvent(
            $kernel,
            fn () => 'response', // Closure controller
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener->onKernelController($event);

        // No exception thrown means the test passed
        $this->assertTrue(true);
    }

    public function testOnKernelControllerDoesNothingWhenNoRequiredScopes(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ScopeAuthorizationListener($tokenStorage, $logger);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $controller = new class {
            public function action(): void
            {
            }
        };

        $event = new ControllerEvent(
            $kernel,
            [$controller, 'action'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener->onKernelController($event);

        // Event controller should not be modified
        $this->assertSame([$controller, 'action'], $event->getController());
    }

    public function testOnKernelControllerReturnsForbiddenWhenNoSecurityToken(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Scope authorization failed: no security token present');

        $listener = new ScopeAuthorizationListener($tokenStorage, $logger);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $controller = new #[RequireScope('profile')] class {
            public function action(): void
            {
            }
        };

        $event = new ControllerEvent(
            $kernel,
            [$controller, 'action'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener->onKernelController($event);

        // Controller should be replaced with forbidden response
        $newController = $event->getController();
        $this->assertIsCallable($newController);

        $response = $newController();
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertSame('insufficient_scope', $data['error']);
        $this->assertSame('Authentication required', $data['error_description']);
    }

    public function testOnKernelControllerReturnsForbiddenWhenNoAccessTokenInRequest(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Scope authorization failed: no access token in request context');

        $listener = new ScopeAuthorizationListener($tokenStorage, $logger);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        // No oauth2_access_token attribute set

        $controller = new #[RequireScope('profile')] class {
            public function action(): void
            {
            }
        };

        $event = new ControllerEvent(
            $kernel,
            [$controller, 'action'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener->onKernelController($event);

        // Controller should be replaced with forbidden response
        $newController = $event->getController();
        $this->assertIsCallable($newController);

        $response = $newController();
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertSame('insufficient_scope', $data['error']);
        $this->assertSame('Invalid authentication', $data['error_description']);
    }

    public function testGetRequiredScopesReturnsClassLevelAttributeScopes(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ScopeAuthorizationListener($tokenStorage, $logger);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        // Controller with class-level RequireScope attribute but no method-level
        $controller = new #[RequireScope(['admin', 'write'])] class {
            public function action(): void
            {
            }
        };

        // Create access token with insufficient scopes to trigger the path
        $client = new OAuth2Client('client_id', 'secret_hash', 'Test Client');
        $accessToken = new AccessToken('token_string', $client, new DateTimeImmutable('+1 hour'));
        $accessToken->setScopes(['read']); // Missing 'admin' and 'write'

        $request->attributes->set('oauth2_access_token', $accessToken);

        $token = $this->createMock(TokenInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $event = new ControllerEvent(
            $kernel,
            [$controller, 'action'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Scope authorization failed: insufficient scopes',
                $this->callback(function (array $context): bool {
                    $required = $context['required'] ?? [];
                    $provided = $context['provided'] ?? [];
                    $this->assertIsArray($required);
                    $this->assertIsArray($provided);
                    return in_array('admin', $required, true)
                        && in_array('write', $required, true)
                        && in_array('read', $provided, true);
                })
            );

        $listener->onKernelController($event);

        // Should replace controller with forbidden response
        $newController = $event->getController();
        $this->assertIsCallable($newController);

        $response = $newController();
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testOnKernelControllerSucceedsWithMethodLevelScopeAttribute(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ScopeAuthorizationListener($tokenStorage, $logger);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        // Controller with method-level RequireScope attribute
        $controller = new class {
            #[RequireScope('profile')]
            public function action(): void
            {
            }
        };

        // Create user and access token with required scopes
        $user = new User('test@example.com', 'testuser', 'hashed_password');
        $client = new OAuth2Client('client_id', 'secret_hash', 'Test Client');
        $accessToken = new AccessToken('token_string', $client, new DateTimeImmutable('+1 hour'));
        $accessToken->setUser($user);
        $accessToken->setScopes(['profile', 'email']);

        $request->attributes->set('oauth2_access_token', $accessToken);

        $token = $this->createMock(TokenInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $event = new ControllerEvent(
            $kernel,
            [$controller, 'action'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        // Should not log warning since scopes are sufficient
        $logger->expects($this->never())->method('warning');

        $listener->onKernelController($event);

        // Controller should NOT be replaced
        $this->assertSame([$controller, 'action'], $event->getController());
    }
}
