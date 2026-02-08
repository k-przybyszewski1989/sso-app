<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\InvalidCredentialsException;
use App\Exception\OAuth2\OAuth2Exception;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Handle OAuth2 exceptions
        if ($exception instanceof OAuth2Exception) {
            $event->setResponse(new JsonResponse(
                [
                    'error' => $exception->getError(),
                    'error_description' => $exception->getErrorDescription(),
                ],
                $exception->getStatusCode()
            ));

            return;
        }

        // Handle invalid credentials exception
        if ($exception instanceof InvalidCredentialsException) {
            $event->setResponse(new JsonResponse(
                [
                    'error' => 'invalid_credentials',
                    'error_description' => $exception->getMessage(),
                ],
                Response::HTTP_UNAUTHORIZED
            ));

            return;
        }

        // Handle user registration/validation errors
        if ($exception instanceof InvalidArgumentException) {
            // Check if it's from user registration service
            if (str_contains($exception->getMessage(), 'already taken')
                || str_contains($exception->getMessage(), 'Invalid email')
                || str_contains($exception->getMessage(), 'Invalid username')) {
                $event->setResponse(new JsonResponse(
                    [
                        'error' => 'validation_error',
                        'error_description' => $exception->getMessage(),
                    ],
                    Response::HTTP_BAD_REQUEST
                ));

                return;
            }
        }
    }
}
