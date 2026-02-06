<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onKernelException')]
class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        # Only handle exceptions for API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof UnauthorizedHttpException => [
                'success' => false,
                'error' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Nicht autorisiert',
            ],
            $exception instanceof AccessDeniedHttpException,
            $exception instanceof AccessDeniedException => [
                'success' => false,
                'error' => 'Zugriff verweigert',
            ],
            $exception instanceof NotFoundHttpException => [
                'success' => false,
                'error' => 'Ressource nicht gefunden',
            ],
            $exception instanceof \InvalidArgumentException => [
                'success' => false,
                'error' => $exception->getMessage(),
            ],
            default => [
                'success' => false,
                'error' => 'Interner Serverfehler',
            ],
        };

        $statusCode = match (true) {
            $exception instanceof UnauthorizedHttpException => 401,
            $exception instanceof AccessDeniedHttpException,
            $exception instanceof AccessDeniedException => 403,
            $exception instanceof NotFoundHttpException => 404,
            $exception instanceof \InvalidArgumentException => 422,
            default => 500,
        };

        $event->setResponse(new JsonResponse($response, $statusCode));
    }
}
