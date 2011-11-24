<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * ExceptionListener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionListener
{
    private $controller;
    private $logger;

    public function __construct($controller, LoggerInterface $logger = null)
    {
        $this->controller = $controller;
        $this->logger = $logger;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        static $handling;

        if (true === $handling) {
            return false;
        }

        $handling = true;

        $exception = $event->getException();
        $request = $event->getRequest();

        if (null !== $this->logger) {
            $message = sprintf('%s: %s (uncaught exception) at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine());
            if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500) {
                $this->logger->crit($message);
            } else {
                $this->logger->err($message);
            }
        } else {
            error_log(sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
        }

        $logger = $this->logger instanceof DebugLoggerInterface ? $this->logger : null;

        $flattenException = FlattenException::create($exception);
        if ($exception instanceof HttpExceptionInterface) {
            $flattenException->setStatusCode($exception->getStatusCode());
            $flattenException->setHeaders($exception->getHeaders());
        }

        $attributes = array(
            '_controller' => $this->controller,
            'exception'   => $flattenException,
            'logger'      => $logger,
            'format'      => $request->getRequestFormat(),
        );

        $request = $request->duplicate(null, null, $attributes);

        try {
            $response = $event->getKernel()->handle($request, HttpKernelInterface::SUB_REQUEST, true);
        } catch (\Exception $e) {
            $message = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $e->getMessage());
            if (null !== $this->logger) {
                if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500) {
                    $this->logger->crit($message);
                } else {
                    $this->logger->err($message);
                }
            } else {
                error_log($message);
            }

            // set handling to false otherwise it wont be able to handle further more
            $handling = false;

            // re-throw the exception as this is a catch-all
            throw $exception;
        }

        $event->setResponse($response);

        $handling = false;
    }
}
