<?php

declare(strict_types=1);

namespace App\Request\ParamConverter;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class RequestExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onValidationException'];
    }

    public function onValidationException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (false === ($exception instanceof RequestValidationException)) {
            return;
        }

        $event->setResponse(new JsonResponse(
            $exception->getViolations(),
            Response::HTTP_BAD_REQUEST,
        ));
    }
}
