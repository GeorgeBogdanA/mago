<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\ProblemResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns any uncaught error under /v1/ (404, 405, 500, ...) into problem+json, without a stack trace.
 *
 * Priority -64: after Symfony logs the exception (priority 0), before its HTML/debug error page (-128).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class ProblemJsonExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/v1/')) {
            return;
        }

        $throwable = $event->getThrowable();
        $isHttp = $throwable instanceof HttpExceptionInterface;
        $status = $isHttp ? $throwable->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $title = Response::$statusTexts[$status] ?? 'Error';
        // Never the exception message: it can contain internals (SQL, paths, hostnames).
        $detail = $status >= 500 ? 'An unexpected error occurred.' : $title.'.';

        $response = ProblemResponse::create($status, 'about:blank', $title, $detail);
        if ($isHttp) {
            $response->headers->add($throwable->getHeaders()); // e.g. "Allow" on a 405
        }
        $event->setResponse($response);
    }
}
