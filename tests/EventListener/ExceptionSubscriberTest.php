<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ExceptionSubscriber;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidCredentialsException;
use App\Exception\OAuth2\InvalidClientException;
use App\Exception\OAuth2\InvalidGrantException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

#[AllowMockObjectsWithoutExpectations]
final class ExceptionSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsReturnsCorrectMapping(): void
    {
        $events = ExceptionSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        $this->assertEquals(['onKernelException', 10], $events[KernelEvents::EXCEPTION]);
    }

    public function testOAuth2ExceptionConvertedToJsonResponse(): void
    {
        $exception = new InvalidClientException('Client authentication failed');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('error_description', $data);
        $this->assertEquals('invalid_client', $data['error']);
        $this->assertEquals('Client authentication failed', $data['error_description']);
    }

    public function testEntityNotFoundExceptionReturns404(): void
    {
        $exception = new EntityNotFoundException('OAuth2Client', 'nonexistent-id');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('error_description', $data);
        $this->assertEquals('not_found', $data['error']);
        $this->assertIsString($data['error_description']);
        $this->assertStringContainsString('OAuth2Client not found', $data['error_description']);
    }

    public function testInvalidCredentialsExceptionReturns401(): void
    {
        $exception = new InvalidCredentialsException('Wrong password');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('error_description', $data);
        $this->assertEquals('invalid_credentials', $data['error']);
        $this->assertEquals('Wrong password', $data['error_description']);
    }

    public function testInvalidArgumentExceptionForEmailValidation(): void
    {
        $exception = new InvalidArgumentException('Invalid email format');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('error_description', $data);
        $this->assertEquals('validation_error', $data['error']);
        $this->assertEquals('Invalid email format', $data['error_description']);
    }

    public function testInvalidArgumentExceptionForUsernameTaken(): void
    {
        $exception = new InvalidArgumentException('Username is already taken');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        $this->assertEquals('validation_error', $data['error']);
        $this->assertIsString($data['error_description']);
        $this->assertStringContainsString('already taken', $data['error_description']);
    }

    public function testInvalidArgumentExceptionForInvalidUsername(): void
    {
        $exception = new InvalidArgumentException('Invalid username format');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        $this->assertEquals('validation_error', $data['error']);
        $this->assertIsString($data['error_description']);
        $this->assertStringContainsString('Invalid username', $data['error_description']);
    }

    public function testNonHandledExceptionDoesNotSetResponse(): void
    {
        $exception = new RuntimeException('Some random error');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        // Response should not be set for non-handled exceptions
        $response = $event->getResponse();
        $this->assertNull($response);
    }

    public function testMultipleOAuth2ExceptionTypes(): void
    {
        // Test with different OAuth2Exception subclasses
        $exception = new InvalidGrantException('Invalid authorization code');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('error_description', $data);
    }

    public function testInvalidArgumentExceptionWithoutKeywordsIsNotHandled(): void
    {
        $exception = new InvalidArgumentException('Some other validation error');
        $event = $this->createExceptionEvent($exception);

        $subscriber = new ExceptionSubscriber();
        $subscriber->onKernelException($event);

        // Response should not be set for InvalidArgumentException without keywords
        $response = $event->getResponse();
        $this->assertNull($response);
    }

    private function createExceptionEvent(Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');

        return new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );
    }
}
